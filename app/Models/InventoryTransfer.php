<?php

namespace App\Models;

use App\Support\BusinessTime;
use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTransfer extends Model
{
    use HasFactory, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'transfer_number',
        'source_location_id',
        'destination_location_id',
        'requested_by',
        'approved_by',
        'approved_at',
        'transferred_by',
        'received_by',
        'status',
        'sent_at',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryTransfer $transfer) {
            if (auth()->check()) {
                $transfer->created_by ??= auth()->id();
                $transfer->updated_by ??= auth()->id();
                $transfer->requested_by ??= auth()->id();
            }

            if (empty($transfer->transfer_number)) {
                $date = BusinessTime::localDateKey();
                [$start, $end] = BusinessTime::utcRangeForLocalDate(BusinessTime::today());
                $lastSeq = static::whereBetween('created_at', [$start, $end])->lockForUpdate()->count();
                $transfer->transfer_number = sprintf('TRN-%s-%03d', $date, $lastSeq + 1);
            }
        });

        static::updating(function (InventoryTransfer $transfer) {
            if (auth()->check()) {
                $transfer->updated_by = auth()->id();
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }
}
