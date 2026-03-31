<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryLocation extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'is_default_purchase_destination',
        'is_default_recipe_deduction_location',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default_purchase_destination' => 'boolean',
        'is_default_recipe_deduction_location' => 'boolean',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryLocationStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'destination_location_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'source_location_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'destination_location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
