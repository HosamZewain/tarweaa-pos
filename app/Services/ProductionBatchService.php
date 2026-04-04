<?php

namespace App\Services;

use App\Enums\InventoryItemType;
use App\Enums\InventoryTransactionType;
use App\Enums\ProductionBatchStatus;
use App\Models\InventoryLocation;
use App\Models\ProductionBatch;
use App\Models\InventoryLocationStock;
use App\Models\ProductionRecipe;
use App\Models\ProductionRecipeLine;
use Illuminate\Support\Facades\DB;

class ProductionBatchService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryLocationService $inventoryLocationService,
        private readonly AdminActivityLogService $adminActivityLogService,
    ) {}

    /**
     * @param array<int|string, mixed> $inputQuantities
     */
    public function produce(
        ProductionRecipe $recipe,
        float $actualOutputQuantity,
        int $actorId,
        ?InventoryLocation $location = null,
        array $inputQuantities = [],
        ?string $notes = null,
        float $wasteQuantity = 0,
        ?string $wasteNotes = null,
        ?int $approvedBy = null,
    ): ProductionBatch {
        if ($actualOutputQuantity <= 0) {
            throw new \InvalidArgumentException('كمية الإنتاج الفعلية يجب أن تكون موجبة.');
        }

        if ($wasteQuantity < 0) {
            throw new \InvalidArgumentException('كمية الفاقد لا يمكن أن تكون سالبة.');
        }

        return DB::transaction(function () use ($recipe, $actualOutputQuantity, $actorId, $location, $inputQuantities, $notes, $wasteQuantity, $wasteNotes, $approvedBy): ProductionBatch {
            $recipe = ProductionRecipe::query()
                ->with(['preparedItem', 'lines.inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($recipe->id);

            if (!$recipe->is_active) {
                throw new \RuntimeException('لا يمكن إنشاء batch من وصفة إنتاج غير نشطة.');
            }

            $preparedItem = $recipe->preparedItem;

            if (!$preparedItem) {
                throw new \RuntimeException('الصنف المحضر المرتبط بوصفة الإنتاج غير موجود.');
            }

            if ($preparedItem->item_type !== InventoryItemType::PreparedItem) {
                throw new \RuntimeException('وصفة الإنتاج يجب أن ترتبط بمادة مخزنية من نوع منتج مُحضّر.');
            }

            if ($recipe->lines->isEmpty()) {
                throw new \RuntimeException('لا يمكن إنشاء batch بدون مكونات في وصفة الإنتاج.');
            }

            $location ??= $this->inventoryLocationService->defaultProductionLocation();

            if (!$location || !$location->is_active) {
                throw new \RuntimeException('لا يوجد موقع إنتاج نشط افتراضي.');
            }

            $actualQuantitiesByLineId = $this->normalizeInputQuantities($inputQuantities);
            $plannedOutputQuantity = round((float) $recipe->output_quantity, 3);
            $yieldVarianceQuantity = round($actualOutputQuantity - $plannedOutputQuantity, 3);
            $yieldVariancePercentage = $plannedOutputQuantity > 0
                ? round(($yieldVarianceQuantity / $plannedOutputQuantity) * 100, 2)
                : 0.0;

            $batch = ProductionBatch::query()->create([
                'batch_number' => ProductionBatch::generateBatchNumber(),
                'prepared_item_id' => $preparedItem->id,
                'production_recipe_id' => $recipe->id,
                'inventory_location_id' => $location->id,
                'status' => ProductionBatchStatus::Completed,
                'planned_output_quantity' => $plannedOutputQuantity,
                'actual_output_quantity' => round($actualOutputQuantity, 3),
                'waste_quantity' => round($wasteQuantity, 3),
                'output_unit' => $recipe->output_unit ?: $preparedItem->unit,
                'total_input_cost' => 0,
                'unit_cost' => 0,
                'yield_variance_quantity' => $yieldVarianceQuantity,
                'yield_variance_percentage' => $yieldVariancePercentage,
                'notes' => $notes,
                'waste_notes' => $wasteNotes,
                'produced_at' => now(),
                'produced_by' => $actorId,
                'approved_by' => $approvedBy ?? $actorId,
                'approved_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $totalInputCost = 0.0;

            foreach ($recipe->lines as $line) {
                $this->assertValidProductionLine($line, $preparedItem->id);

                $plannedQuantity = round((float) $line->quantity, 3);
                $actualQuantity = round((float) ($actualQuantitiesByLineId[$line->id] ?? $plannedQuantity), 3);

                if ($actualQuantity <= 0) {
                    throw new \RuntimeException("كمية المكون [{$line->inventoryItem?->name}] في batch يجب أن تكون موجبة.");
                }

                $baseQuantity = round($actualQuantity * (float) $line->unit_conversion_rate, 6);

                if ($baseQuantity <= 0) {
                    throw new \RuntimeException("معامل التحويل للمكون [{$line->inventoryItem?->name}] أدى إلى كمية أساسية غير صالحة.");
                }

                $transaction = $this->inventoryService->deductStock(
                    item: $line->inventoryItem,
                    quantity: $baseQuantity,
                    actorId: $actorId,
                    type: InventoryTransactionType::ProductionConsumption,
                    refType: 'production_batch',
                    refId: $batch->id,
                    notes: "استهلاك إنتاج batch {$batch->batch_number} للصنف {$preparedItem->name}",
                    location: $location,
                    updateGlobalStock: true,
                );

                $lineCost = round((float) ($transaction->total_cost ?? ((float) $transaction->unit_cost * $baseQuantity)), 2);
                $totalInputCost = round($totalInputCost + $lineCost, 2);

                $batch->lines()->create([
                    'production_recipe_line_id' => $line->id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'inventory_transaction_id' => $transaction->id,
                    'planned_quantity' => $plannedQuantity,
                    'actual_quantity' => $actualQuantity,
                    'base_quantity' => $baseQuantity,
                    'unit' => $line->unit,
                    'unit_conversion_rate' => $line->unit_conversion_rate,
                    'unit_cost' => $transaction->unit_cost,
                    'total_cost' => $lineCost,
                    'sort_order' => $line->sort_order,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            }

            $outputUnitCost = round($totalInputCost / $actualOutputQuantity, 2);

            $this->inventoryService->addStock(
                item: $preparedItem,
                quantity: round($actualOutputQuantity, 3),
                actorId: $actorId,
                type: InventoryTransactionType::ProductionOutput,
                unitCost: $outputUnitCost,
                refType: 'production_batch',
                refId: $batch->id,
                notes: "ناتج إنتاج batch {$batch->batch_number}",
                location: $location,
                updateGlobalStock: true,
            );

            $batch->update([
                'total_input_cost' => $totalInputCost,
                'unit_cost' => $outputUnitCost,
                'updated_by' => $actorId,
            ]);

            $batch = $batch->fresh([
                'preparedItem',
                'productionRecipe.lines.inventoryItem',
                'location',
                'producer',
                'approver',
                'lines.inventoryItem',
                'lines.inventoryTransaction',
            ]);

            $this->adminActivityLogService->logAction(
                action: 'production_batch_completed',
                subject: $batch,
                description: 'تم تنفيذ دفعة إنتاج وتحضير.',
                newValues: [
                    'batch_number' => $batch->batch_number,
                    'prepared_item' => $batch->preparedItem?->name,
                    'location' => $batch->location?->name,
                    'actual_output_quantity' => (float) $batch->actual_output_quantity,
                    'waste_quantity' => (float) $batch->waste_quantity,
                    'unit_cost' => (float) $batch->unit_cost,
                    'total_input_cost' => (float) $batch->total_input_cost,
                    'approved_by' => $batch->approver?->name,
                ],
                meta: [
                    'production_recipe_id' => $batch->production_recipe_id,
                    'inventory_location_id' => $batch->inventory_location_id,
                ],
            );

            return $batch;
        });
    }

    public function void(
        ProductionBatch $batch,
        int $actorId,
        string $reason,
        ?int $approvedBy = null,
    ): ProductionBatch {
        if (blank($reason)) {
            throw new \InvalidArgumentException('سبب إلغاء الدفعة مطلوب.');
        }

        return DB::transaction(function () use ($batch, $actorId, $reason, $approvedBy): ProductionBatch {
            $batch = ProductionBatch::query()
                ->with([
                    'preparedItem',
                    'location',
                    'lines.inventoryItem',
                ])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            if (!$batch->canBeVoided()) {
                throw new \RuntimeException('لا يمكن إلغاء هذه الدفعة.');
            }

            $preparedItem = $batch->preparedItem;
            $location = $batch->location;

            if (!$preparedItem || !$location) {
                throw new \RuntimeException('بيانات الدفعة غير مكتملة ولا يمكن عكسها.');
            }

            $locationStock = InventoryLocationStock::query()
                ->where('inventory_item_id', $preparedItem->id)
                ->where('inventory_location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (!$locationStock || (float) $locationStock->current_stock < (float) $batch->actual_output_quantity) {
                throw new \RuntimeException('لا يمكن إلغاء الدفعة لأن الناتج لم يعد متاحًا كاملًا في موقع الإنتاج.');
            }

            $this->inventoryService->deductStock(
                item: $preparedItem,
                quantity: (float) $batch->actual_output_quantity,
                actorId: $actorId,
                type: InventoryTransactionType::ProductionVoidOutput,
                refType: 'production_batch_void',
                refId: $batch->id,
                notes: "عكس ناتج batch {$batch->batch_number}. السبب: {$reason}",
                location: $location,
                updateGlobalStock: true,
                unitCostOverride: (float) $batch->unit_cost,
            );

            foreach ($batch->lines as $line) {
                $this->inventoryService->addStock(
                    item: $line->inventoryItem,
                    quantity: (float) $line->base_quantity,
                    actorId: $actorId,
                    type: InventoryTransactionType::ProductionVoidInputReturn,
                    unitCost: (float) $line->unit_cost,
                    refType: 'production_batch_void',
                    refId: $batch->id,
                    notes: "رد مدخلات batch {$batch->batch_number}. السبب: {$reason}",
                    location: $location,
                    updateGlobalStock: true,
                );
            }

            $batch->update([
                'status' => ProductionBatchStatus::Cancelled,
                'voided_by' => $actorId,
                'voided_at' => now(),
                'void_reason' => $reason,
                'updated_by' => $actorId,
                'approved_by' => $approvedBy ?? $batch->approved_by ?? $actorId,
            ]);

            $batch = $batch->fresh([
                'preparedItem',
                'location',
                'producer',
                'approver',
                'voidedBy',
                'lines.inventoryItem',
            ]);

            $this->adminActivityLogService->logAction(
                action: 'production_batch_voided',
                subject: $batch,
                description: 'تم إلغاء دفعة إنتاج وعكس أثرها على المخزون.',
                oldValues: [
                    'status' => ProductionBatchStatus::Completed->value,
                ],
                newValues: [
                    'status' => ProductionBatchStatus::Cancelled->value,
                    'void_reason' => $reason,
                    'voided_by' => $batch->voidedBy?->name,
                ],
                meta: [
                    'batch_number' => $batch->batch_number,
                    'inventory_location_id' => $batch->inventory_location_id,
                ],
            );

            return $batch;
        });
    }

    /**
     * @param array<int|string, mixed> $inputQuantities
     * @return array<int, float>
     */
    private function normalizeInputQuantities(array $inputQuantities): array
    {
        $normalized = [];

        foreach ($inputQuantities as $key => $value) {
            if (is_array($value)) {
                $lineId = isset($value['production_recipe_line_id'])
                    ? (int) $value['production_recipe_line_id']
                    : (isset($value['recipe_line_id']) ? (int) $value['recipe_line_id'] : 0);
                $quantity = isset($value['actual_quantity']) ? (float) $value['actual_quantity'] : null;
            } else {
                $lineId = (int) $key;
                $quantity = (float) $value;
            }

            if ($lineId <= 0 || $quantity === null) {
                continue;
            }

            $normalized[$lineId] = round($quantity, 3);
        }

        return $normalized;
    }

    private function assertValidProductionLine(ProductionRecipeLine $line, int $preparedItemId): void
    {
        if (!$line->inventoryItem) {
            throw new \RuntimeException('أحد مكونات وصفة الإنتاج غير موجود.');
        }

        if ($line->inventory_item_id === $preparedItemId) {
            throw new \RuntimeException('لا يمكن أن يستهلك الصنف المحضّر نفسه داخل batch الإنتاج.');
        }
    }
}
