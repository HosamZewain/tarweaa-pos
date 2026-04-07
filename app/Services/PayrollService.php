<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvancePayrollAllocation;
use App\Models\EmployeePenalty;
use App\Models\EmployeeSalary;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    public function getRunForMonth(string $month): ?PayrollRun
    {
        $monthStart = $this->resolveMonthStart($month);

        return PayrollRun::query()
            ->with([
                'generator:id,name',
                'approver:id,name',
                'lines.advanceAllocations.advance.employee.employeeProfile',
            ])
            ->whereDate('month_key', $monthStart->toDateString())
            ->first();
    }

    public function previewMonth(string $month): array
    {
        [$monthStart, $monthEnd] = $this->resolveMonthRange($month);
        $employees = $this->employeesForPayroll();
        $lines = $this->buildLines($employees, $monthStart, $monthEnd);

        return $this->buildPayload(
            monthStart: $monthStart,
            monthEnd: $monthEnd,
            status: 'preview',
            lines: $lines,
        );
    }

    public function generateMonth(string $month, ?User $actor = null): PayrollRun
    {
        [$monthStart, $monthEnd] = $this->resolveMonthRange($month);

        return DB::transaction(function () use ($actor, $monthStart, $monthEnd): PayrollRun {
            $run = PayrollRun::query()
                ->whereDate('month_key', $monthStart->toDateString())
                ->first();

            if ($run?->isApproved()) {
                throw new RuntimeException('تم اعتماد مسير هذا الشهر بالفعل ولا يمكن إعادة توليده.');
            }

            if (!$run) {
                $run = PayrollRun::query()->create([
                    'month_key' => $monthStart->toDateString(),
                    'period_start' => $monthStart->toDateString(),
                    'period_end' => $monthEnd->toDateString(),
                    'status' => 'draft',
                    'generated_at' => now(),
                    'generated_by' => $actor?->id ?? auth()->id(),
                ]);
            } else {
                $run->lines()->delete();
                $run->fill([
                    'period_start' => $monthStart->toDateString(),
                    'period_end' => $monthEnd->toDateString(),
                    'status' => 'draft',
                    'generated_at' => now(),
                    'generated_by' => $actor?->id ?? auth()->id(),
                    'approved_at' => null,
                    'approved_by' => null,
                ])->save();
            }

            $lines = $this->buildLines($this->employeesForPayroll(), $monthStart, $monthEnd);

            foreach ($lines as $lineData) {
                /** @var PayrollRunLine $line */
                $line = $run->lines()->create($lineData['attributes']);

                foreach ($lineData['advance_allocations'] as $allocation) {
                    $line->advanceAllocations()->create($allocation);
                }
            }

            $this->syncRunTotals($run);

            app(AdminActivityLogService::class)->logAction(
                action: 'payroll_generated',
                subject: $run,
                description: 'تم توليد مسير رواتب شهري.',
                newValues: [
                    'month_key' => $run->month_key?->format('Y-m-d'),
                    'employees_count' => $run->employees_count,
                    'total_net_salary' => (float) $run->total_net_salary,
                ],
            );

            return $run->fresh([
                'generator:id,name',
                'approver:id,name',
                'lines.advanceAllocations.advance.employee.employeeProfile',
            ]);
        });
    }

    public function approve(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if ($run->isApproved()) {
            return $run;
        }

        return DB::transaction(function () use ($run, $actor): PayrollRun {
            $run->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor?->id ?? auth()->id(),
            ]);

            app(AdminActivityLogService::class)->logAction(
                action: 'payroll_approved',
                subject: $run->fresh(),
                description: 'تم اعتماد مسير الرواتب الشهري.',
                newValues: [
                    'month_key' => $run->month_key?->format('Y-m-d'),
                    'approved_at' => $run->fresh()->approved_at?->format('Y-m-d H:i:s'),
                    'approved_by' => $actor?->id ?? auth()->id(),
                ],
            );

            return $run->fresh([
                'generator:id,name',
                'approver:id,name',
                'lines.advanceAllocations.advance.employee.employeeProfile',
            ]);
        });
    }

    public function payloadForRun(PayrollRun $run): array
    {
        $run->loadMissing([
            'generator:id,name',
            'approver:id,name',
            'lines.advanceAllocations.advance.employee.employeeProfile',
        ]);

        return $this->buildPayload(
            monthStart: $run->period_start,
            monthEnd: $run->period_end,
            status: $run->status,
            lines: $run->lines->map(function (PayrollRunLine $line): array {
                $advanceAllocations = collect($line->advances_snapshot ?? []);

                if ($advanceAllocations->isEmpty()) {
                    $advanceAllocations = $line->advanceAllocations
                        ->map(fn (EmployeeAdvancePayrollAllocation $allocation): array => [
                            'employee_advance_id' => $allocation->employee_advance_id,
                            'allocated_amount' => (float) $allocation->allocated_amount,
                            'advance_date' => $allocation->advance?->advance_date?->format('Y-m-d'),
                            'original_amount' => (float) ($allocation->advance?->amount ?? 0),
                            'employee_name' => $allocation->advance?->employee?->employeeProfile?->full_name
                                ?: $allocation->advance?->employee?->name
                                ?: $line->employee_name,
                            'notes' => $allocation->advance?->notes,
                        ]);
                }

                return [
                    'attributes' => [
                        'user_id' => $line->user_id,
                        'employee_name' => $line->employee_name,
                        'job_title' => $line->job_title,
                        'salary_effective_from' => $line->salary_effective_from?->toDateString(),
                        'salary_effective_to' => $line->salary_effective_to?->toDateString(),
                        'penalties_count' => $line->penalties_count,
                        'advances_count' => $line->advances_count,
                        'base_salary' => (float) $line->base_salary,
                        'penalties_total' => (float) $line->penalties_total,
                        'advances_total' => (float) $line->advances_total,
                        'net_salary' => (float) $line->net_salary,
                    ],
                    'advance_allocations' => $advanceAllocations
                        ->map(fn (array $advance): array => [
                            'employee_advance_id' => $advance['employee_advance_id'] ?? null,
                            'allocated_amount' => round((float) ($advance['allocated_amount'] ?? 0), 2),
                            'advance_date' => $advance['advance_date'] ?? null,
                            'original_amount' => round((float) ($advance['original_amount'] ?? 0), 2),
                            'employee_name' => $advance['employee_name'] ?? $line->employee_name,
                            'notes' => $advance['notes'] ?? null,
                        ])->all(),
                    'penalties' => collect($line->penalties_snapshot ?? [])
                        ->map(fn (array $penalty): array => [
                            'id' => $penalty['id'] ?? null,
                            'penalty_date' => $penalty['penalty_date'] ?? null,
                            'reason' => $penalty['reason'] ?? null,
                            'amount' => round((float) ($penalty['amount'] ?? 0), 2),
                        ])->all(),
                ];
            })->all(),
            run: $run,
        );
    }

    private function employeesForPayroll(): Collection
    {
        return Employee::query()
            ->manageable()
            ->with([
                'employeeProfile',
                'employeeSalaries',
                'employeePenalties',
                'employeeAdvances',
            ])
            ->orderBy('name')
            ->get();
    }

    private function buildLines(Collection $employees, Carbon $monthStart, Carbon $monthEnd): array
    {
        return $employees
            ->map(function (Employee $employee) use ($monthStart, $monthEnd): ?array {
                $salary = $this->salaryForMonth($employee, $monthEnd);

                if (!$salary) {
                    return null;
                }

                $penalties = $this->penaltiesForMonth($employee, $monthStart, $monthEnd);
                $advances = $this->advanceAllocationsForMonth($employee, $monthEnd);

                $baseSalary = round((float) $salary->amount, 2);
                $penaltiesTotal = round($penalties->sum(fn (EmployeePenalty $penalty): float => (float) $penalty->amount), 2);
                $advancesTotal = round($advances->sum(fn (array $advance): float => (float) $advance['allocated_amount']), 2);
                $netSalary = round($baseSalary - $penaltiesTotal - $advancesTotal, 2);

                return [
                    'attributes' => [
                        'user_id' => $employee->id,
                        'employee_name' => $employee->employeeProfile?->full_name ?: $employee->name,
                        'job_title' => $employee->employeeProfile?->job_title,
                        'salary_effective_from' => $salary->effective_from?->toDateString(),
                        'salary_effective_to' => $salary->effective_to?->toDateString(),
                        'penalties_count' => $penalties->count(),
                        'advances_count' => count($advances),
                        'penalties_snapshot' => $penalties->map(fn (EmployeePenalty $penalty): array => [
                            'id' => $penalty->id,
                            'penalty_date' => $penalty->penalty_date?->format('Y-m-d'),
                            'reason' => $penalty->reason,
                            'amount' => round((float) $penalty->amount, 2),
                        ])->all(),
                        'advances_snapshot' => $advances,
                        'base_salary' => $baseSalary,
                        'penalties_total' => $penaltiesTotal,
                        'advances_total' => $advancesTotal,
                        'net_salary' => $netSalary,
                    ],
                    'penalties' => $penalties->map(fn (EmployeePenalty $penalty): array => [
                        'id' => $penalty->id,
                        'penalty_date' => $penalty->penalty_date?->format('Y-m-d'),
                        'reason' => $penalty->reason,
                        'amount' => round((float) $penalty->amount, 2),
                    ])->all(),
                    'advance_allocations' => $advances,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function salaryForMonth(Employee $employee, Carbon $monthEnd): ?EmployeeSalary
    {
        return $employee->employeeSalaries
            ->first(function (EmployeeSalary $salary) use ($monthEnd): bool {
                if (!$salary->effective_from || $salary->effective_from->gt($monthEnd)) {
                    return false;
                }

                return !$salary->effective_to || !$salary->effective_to->lt($monthEnd);
            });
    }

    private function penaltiesForMonth(Employee $employee, Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return $employee->employeePenalties
            ->filter(function (EmployeePenalty $penalty) use ($monthStart, $monthEnd): bool {
                return $penalty->is_active
                    && $penalty->penalty_date !== null
                    && $penalty->penalty_date->betweenIncluded($monthStart, $monthEnd);
            })
            ->values();
    }

    private function advanceAllocationsForMonth(Employee $employee, Carbon $monthEnd): Collection
    {
        return $employee->employeeAdvances
            ->filter(function (EmployeeAdvance $advance) use ($monthEnd): bool {
                return !$advance->isCancelled()
                    && $advance->advance_date !== null
                    && !$advance->advance_date->gt($monthEnd);
            })
            ->map(function (EmployeeAdvance $advance) use ($employee): ?array {
                $allocatedToApprovedRuns = (float) EmployeeAdvancePayrollAllocation::query()
                    ->where('employee_advance_id', $advance->id)
                    ->whereHas('payrollRunLine.payrollRun', fn ($query) => $query->where('status', 'approved'))
                    ->sum('allocated_amount');

                $remaining = round((float) $advance->amount - $allocatedToApprovedRuns, 2);

                if ($remaining <= 0) {
                    return null;
                }

                return [
                    'employee_advance_id' => $advance->id,
                    'allocated_amount' => $remaining,
                    'advance_date' => $advance->advance_date?->format('Y-m-d'),
                    'original_amount' => round((float) $advance->amount, 2),
                    'employee_name' => $employee->employeeProfile?->full_name ?: $employee->name,
                    'notes' => $advance->notes,
                ];
            })
            ->filter()
            ->values();
    }

    private function syncRunTotals(PayrollRun $run): void
    {
        $run->loadMissing('lines');

        $run->update([
            'employees_count' => $run->lines->count(),
            'total_base_salary' => round($run->lines->sum(fn (PayrollRunLine $line): float => (float) $line->base_salary), 2),
            'total_penalties' => round($run->lines->sum(fn (PayrollRunLine $line): float => (float) $line->penalties_total), 2),
            'total_advances' => round($run->lines->sum(fn (PayrollRunLine $line): float => (float) $line->advances_total), 2),
            'total_net_salary' => round($run->lines->sum(fn (PayrollRunLine $line): float => (float) $line->net_salary), 2),
        ]);
    }

    private function buildPayload(
        Carbon|string $monthStart,
        Carbon|string $monthEnd,
        string $status,
        array $lines,
        ?PayrollRun $run = null,
    ): array {
        $normalizedLines = collect($lines)->map(function (array $line): array {
            $attributes = $line['attributes'];

            return [
                'employee_name' => $attributes['employee_name'],
                'job_title' => $attributes['job_title'],
                'base_salary' => round((float) $attributes['base_salary'], 2),
                'penalties_total' => round((float) $attributes['penalties_total'], 2),
                'advances_total' => round((float) $attributes['advances_total'], 2),
                'net_salary' => round((float) $attributes['net_salary'], 2),
                'penalties_count' => (int) $attributes['penalties_count'],
                'advances_count' => (int) $attributes['advances_count'],
                'salary_effective_from' => $attributes['salary_effective_from'],
                'salary_effective_to' => $attributes['salary_effective_to'] ?: 'مستمر',
            ];
        })->values();

        $allPenaltyRows = collect($lines)
            ->flatMap(fn (array $line) => collect($line['penalties'] ?? [])->map(fn (array $penalty): array => [
                'employee_name' => $line['attributes']['employee_name'],
                'penalty_date' => $penalty['penalty_date'],
                'reason' => $penalty['reason'],
                'amount' => $penalty['amount'],
            ]))
            ->values();

        $allAdvanceRows = collect($lines)
            ->flatMap(fn (array $line) => collect($line['advance_allocations'] ?? [])->map(fn (array $advance): array => [
                'employee_name' => $advance['employee_name'],
                'advance_date' => $advance['advance_date'],
                'original_amount' => round((float) $advance['original_amount'], 2),
                'allocated_amount' => round((float) $advance['allocated_amount'], 2),
                'notes' => $advance['notes'],
            ]))
            ->values();

        return [
            'run' => [
                'id' => $run?->id,
                'status' => $status,
                'month_key' => BusinessTime::formatDate($monthStart),
                'period_start' => BusinessTime::formatDate($monthStart),
                'period_end' => BusinessTime::formatDate($monthEnd),
                'generated_at' => $run?->generated_at?->format('Y-m-d H:i') ?: '—',
                'generated_by' => $run?->generator?->name ?: '—',
                'approved_at' => $run?->approved_at?->format('Y-m-d H:i') ?: '—',
                'approved_by' => $run?->approver?->name ?: '—',
            ],
            'summary' => [
                'employees_count' => $normalizedLines->count(),
                'total_base_salary' => round($normalizedLines->sum('base_salary'), 2),
                'total_penalties' => round($normalizedLines->sum('penalties_total'), 2),
                'total_advances' => round($normalizedLines->sum('advances_total'), 2),
                'total_net_salary' => round($normalizedLines->sum('net_salary'), 2),
            ],
            'lines' => $normalizedLines->all(),
            'penalties' => $allPenaltyRows->all(),
            'advances' => $allAdvanceRows->all(),
        ];
    }

    private function resolveMonthRange(string $month): array
    {
        $monthStart = $this->resolveMonthStart($month);

        return [$monthStart, $monthStart->copy()->endOfMonth()];
    }

    private function resolveMonthStart(string $month): Carbon
    {
        return Carbon::createFromFormat('Y-m', $month, BusinessTime::timezone())->startOfMonth();
    }
}
