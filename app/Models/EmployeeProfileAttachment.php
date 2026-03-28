<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfileAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'uploaded_by',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeProfileAttachment $attachment): void {
            if (auth()->check()) {
                $attachment->uploaded_by ??= auth()->id();
            }
        });
    }
}
