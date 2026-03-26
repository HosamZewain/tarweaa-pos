<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Approve an expense.
     * Records the user who performed the approval.
     */
    public function approve(Expense $expense, int $actorId): void
    {
        $expense->update([
            'approved_by' => $actorId,
            'approved_at' => now(),
            'updated_by'  => $actorId,
        ]);
    }
}
