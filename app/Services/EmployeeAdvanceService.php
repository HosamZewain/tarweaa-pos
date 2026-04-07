<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use Illuminate\Support\Facades\DB;

class EmployeeAdvanceService
{
    public function cancel(EmployeeAdvance $advance, ?string $reason = null): EmployeeAdvance
    {
        if ($advance->isCancelled()) {
            return $advance;
        }

        return DB::transaction(function () use ($advance, $reason): EmployeeAdvance {
            $oldValues = [
                'status' => $advance->status,
                'cancelled_at' => $advance->cancelled_at?->format('Y-m-d H:i:s'),
                'cancelled_by' => $advance->cancelled_by,
                'cancellation_reason' => $advance->cancellation_reason,
            ];

            app(AdminActivityLogService::class)->withoutModelLogging(function () use ($advance, $reason): void {
                $advance->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => filled($reason) ? $reason : null,
                ]);
            });

            app(AdminActivityLogService::class)->logAction(
                action: 'cancelled',
                subject: $advance->fresh(),
                description: 'تم إلغاء سلفة موظف.',
                oldValues: $oldValues,
                newValues: [
                    'status' => 'cancelled',
                    'cancelled_at' => $advance->fresh()->cancelled_at?->format('Y-m-d H:i:s'),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => filled($reason) ? $reason : null,
                ],
            );

            return $advance->fresh();
        });
    }
}
