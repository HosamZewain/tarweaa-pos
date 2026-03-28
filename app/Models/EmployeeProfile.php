<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    use HasFactory, HasAuditFields;

    protected $fillable = [
        'user_id',
        'full_name',
        'job_title',
        'hired_at',
        'profile_image',
        'notes',
    ];

    protected $casts = [
        'hired_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeProfileAttachment::class)->latest('id');
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeProfile $profile): void {
            if (auth()->check()) {
                $profile->created_by ??= auth()->id();
                $profile->updated_by ??= auth()->id();
            }
        });

        static::updating(function (EmployeeProfile $profile): void {
            if (auth()->check()) {
                $profile->updated_by = auth()->id();
            }
        });
    }
}
