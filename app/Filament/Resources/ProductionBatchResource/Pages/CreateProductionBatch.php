<?php

namespace App\Filament\Resources\ProductionBatchResource\Pages;

use App\Filament\Resources\ProductionBatchResource;
use App\Models\InventoryLocation;
use App\Models\ProductionBatch;
use App\Models\ProductionRecipe;
use App\Services\ManagerVerificationService;
use App\Services\ProductionBatchService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProductionBatch extends CreateRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function handleRecordCreation(array $data): ProductionBatch
    {
        $approverId = (int) ($data['approver_id'] ?? 0);
        $approverPin = (string) ($data['approver_pin'] ?? '');
        $approver = app(ManagerVerificationService::class)->findApprover($approverId);

        if (!$approver) {
            throw ValidationException::withMessages([
                'data.approver_id' => 'المعتمد المحدد غير صالح.',
            ]);
        }

        if (!app(ManagerVerificationService::class)->verifyPin($approver, $approverPin)) {
            throw ValidationException::withMessages([
                'data.approver_pin' => 'PIN المعتمد غير صحيح.',
            ]);
        }

        $recipe = ProductionRecipe::query()->findOrFail($data['production_recipe_id']);
        $location = InventoryLocation::query()->findOrFail($data['inventory_location_id']);

        return app(ProductionBatchService::class)->produce(
            recipe: $recipe,
            actualOutputQuantity: (float) $data['actual_output_quantity'],
            actorId: auth()->id(),
            location: $location,
            inputQuantities: $data['input_quantities_payload'] ?? [],
            notes: $data['notes'] ?? null,
            wasteQuantity: (float) ($data['waste_quantity'] ?? 0),
            wasteNotes: $data['waste_notes'] ?? null,
            approvedBy: $approver->id,
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
