<?php

namespace App\Models;

use App\Enums\ProductionBatchStatus;
use App\Support\BusinessTime;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'batch_number',
        'prepared_item_id',
        'production_recipe_id',
        'inventory_location_id',
        'status',
        'planned_output_quantity',
        'actual_output_quantity',
        'waste_quantity',
        'output_unit',
        'total_input_cost',
        'unit_cost',
        'yield_variance_quantity',
        'yield_variance_percentage',
        'notes',
        'waste_notes',
        'produced_at',
        'produced_by',
        'approved_by',
        'approved_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'status' => ProductionBatchStatus::class,
        'planned_output_quantity' => 'decimal:3',
        'actual_output_quantity' => 'decimal:3',
        'waste_quantity' => 'decimal:3',
        'total_input_cost' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'yield_variance_quantity' => 'decimal:3',
        'yield_variance_percentage' => 'decimal:2',
        'produced_at' => 'datetime',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function preparedItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'prepared_item_id');
    }

    public function productionRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionBatchLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function generateBatchNumber(): string
    {
        $date = BusinessTime::localDateKey();
        [$start, $end] = BusinessTime::utcRangeForLocalDate(BusinessTime::today());

        $lastSeq = static::query()
            ->whereBetween('created_at', [$start, $end])
            ->lockForUpdate()
            ->count();

        return sprintf('PRD-%s-%04d', $date, $lastSeq + 1);
    }

    public function isCompleted(): bool
    {
        return $this->status === ProductionBatchStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProductionBatchStatus::Cancelled;
    }

    public function canBeVoided(): bool
    {
        return $this->isCompleted() && $this->voided_at === null;
    }
}
