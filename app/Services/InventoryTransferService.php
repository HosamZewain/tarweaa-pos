<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryTransfer;
use Illuminate\Support\Facades\DB;

class InventoryTransferService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function approve(InventoryTransfer $transfer, int $actorId): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId) {
            $transfer = InventoryTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if (!$transfer->isDraft()) {
                throw new \RuntimeException('لا يمكن اعتماد تحويل ليس في حالة مسودة.');
            }

            $transfer->update([
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $transfer->fresh(['sourceLocation', 'destinationLocation', 'items.inventoryItem']);
        });
    }

    public function send(InventoryTransfer $transfer, int $actorId): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId) {
            $transfer = InventoryTransfer::query()
                ->with(['sourceLocation', 'destinationLocation', 'items.inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            $this->validateTransferForMovement($transfer);

            if (!$transfer->isDraft()) {
                throw new \RuntimeException('لا يمكن إرسال تحويل إلا من حالة المسودة.');
            }

            foreach ($transfer->items as $item) {
                $quantity = (float) $item->quantity_sent;

                if ($quantity <= 0) {
                    throw new \RuntimeException('كل بنود التحويل يجب أن تحتوي على كمية صادرة موجبة.');
                }

                $item->update([
                    'unit' => $item->unit ?: $item->inventoryItem->unit,
                    'unit_cost' => $item->unit_cost ?? $item->inventoryItem->unit_cost,
                    'updated_by' => $actorId,
                ]);

                $this->inventoryService->deductStock(
                    item: $item->inventoryItem,
                    quantity: $quantity,
                    actorId: $actorId,
                    type: InventoryTransactionType::TransferOut,
                    refType: 'inventory_transfer',
                    refId: $transfer->id,
                    notes: "تحويل مخزون صادر رقم {$transfer->transfer_number}",
                    location: $transfer->sourceLocation,
                    updateGlobalStock: false,
                );
            }

            $transfer->update([
                'status' => 'sent',
                'sent_at' => now(),
                'transferred_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $transfer->fresh(['sourceLocation', 'destinationLocation', 'items.inventoryItem']);
        });
    }

    public function receive(InventoryTransfer $transfer, int $actorId): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId) {
            $transfer = InventoryTransfer::query()
                ->with(['sourceLocation', 'destinationLocation', 'items.inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            if (!$transfer->isSent()) {
                throw new \RuntimeException('لا يمكن استلام تحويل إلا بعد إرساله.');
            }

            foreach ($transfer->items as $item) {
                $quantitySent = (float) $item->quantity_sent;
                $quantityReceived = (float) $item->quantity_received;

                if ($quantityReceived <= 0) {
                    $quantityReceived = $quantitySent;
                }

                if ($quantitySent <= 0 || $quantityReceived <= 0) {
                    throw new \RuntimeException('كل بنود التحويل يجب أن تحتوي على كمية موجبة للاستلام.');
                }

                if ($quantityReceived > $quantitySent) {
                    throw new \RuntimeException('الكمية المستلمة لا يمكن أن تتجاوز الكمية المرسلة.');
                }

                $this->inventoryService->addStock(
                    item: $item->inventoryItem,
                    quantity: $quantityReceived,
                    actorId: $actorId,
                    type: InventoryTransactionType::TransferIn,
                    unitCost: (float) ($item->unit_cost ?? $item->inventoryItem->unit_cost),
                    refType: 'inventory_transfer',
                    refId: $transfer->id,
                    notes: "تحويل مخزون وارد رقم {$transfer->transfer_number}",
                    location: $transfer->destinationLocation,
                    updateGlobalStock: false,
                );

                $item->update([
                    'quantity_received' => $quantityReceived,
                    'updated_by' => $actorId,
                ]);
            }

            $transfer->update([
                'status' => 'received',
                'received_at' => now(),
                'received_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $transfer->fresh(['sourceLocation', 'destinationLocation', 'items.inventoryItem']);
        });
    }

    public function cancel(InventoryTransfer $transfer, int $actorId): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $actorId) {
            $transfer = InventoryTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if (!$transfer->isDraft()) {
                throw new \RuntimeException('إلغاء التحويل متاح فقط في حالة المسودة.');
            }

            $transfer->update([
                'status' => 'cancelled',
                'updated_by' => $actorId,
            ]);

            return $transfer->fresh(['sourceLocation', 'destinationLocation', 'items.inventoryItem']);
        });
    }

    private function validateTransferForMovement(InventoryTransfer $transfer): void
    {
        if ($transfer->items->isEmpty()) {
            throw new \RuntimeException('لا يمكن تنفيذ تحويل بدون بنود.');
        }

        if (!$transfer->sourceLocation || !$transfer->destinationLocation) {
            throw new \RuntimeException('يجب تحديد موقع المصدر والوجهة للتحويل.');
        }

        if ($transfer->source_location_id === $transfer->destination_location_id) {
            throw new \RuntimeException('لا يمكن التحويل إلى نفس الموقع.');
        }
    }
}
