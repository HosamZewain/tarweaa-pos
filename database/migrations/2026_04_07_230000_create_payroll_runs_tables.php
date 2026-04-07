<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('month_key')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->unsignedInteger('employees_count')->default(0);
            $table->decimal('total_base_salary', 14, 2)->default(0);
            $table->decimal('total_penalties', 14, 2)->default(0);
            $table->decimal('total_advances', 14, 2)->default(0);
            $table->decimal('total_net_salary', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'month_key']);
        });

        Schema::create('payroll_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_name');
            $table->string('job_title')->nullable();
            $table->date('salary_effective_from')->nullable();
            $table->date('salary_effective_to')->nullable();
            $table->unsignedInteger('penalties_count')->default(0);
            $table->unsignedInteger('advances_count')->default(0);
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('penalties_total', 14, 2)->default(0);
            $table->decimal('advances_total', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id']);
            $table->index(['user_id', 'net_salary']);
        });

        Schema::create('employee_advance_payroll_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
            $table->foreignId('employee_advance_id')->constrained('employee_advances')->cascadeOnDelete();
            $table->decimal('allocated_amount', 14, 2);
            $table->timestamps();

            $table->unique(['payroll_run_line_id', 'employee_advance_id'], 'payroll_line_advance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_payroll_allocations');
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
    }
};
