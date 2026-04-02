<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_user_id',
        'action',
        'module',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'old_values',
        'new_values',
        'meta',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'restored' => 'استعادة',
            'approved' => 'اعتماد',
            'cancelled' => 'إلغاء',
            'opened' => 'فتح',
            'closed' => 'إغلاق',
            'cash_in_recorded' => 'إيداع نقدي',
            'cash_out_recorded' => 'سحب نقدي',
            'special_settlement_applied' => 'تسوية خاصة',
            'stock_adjusted' => 'تعديل مخزون',
            'stock_added' => 'إضافة مخزون',
            'backup_created' => 'إنشاء نسخة احتياطية',
            'backup_restored' => 'استعادة نسخة احتياطية',
            'operational_data_reset' => 'إعادة تهيئة بيانات',
            'bulk_assigned' => 'إسناد جماعي',
            'toggled' => 'تغيير حالة',
            default => $this->action,
        };
    }
}
