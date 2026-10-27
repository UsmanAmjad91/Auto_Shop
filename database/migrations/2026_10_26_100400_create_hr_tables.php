<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->dateTime('break_start')->nullable();
            $table->dateTime('break_end')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->decimal('regular_hours', 6, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->string('status', 20)->default('present')->index(); // present, absent, late, leave, holiday
            $table->string('source', 20)->default('manual'); // self_service, manual, import
            $table->text('notes')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('frequency', 20); // weekly, biweekly, monthly, custom
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, approved, closed
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('pay_rate_id')->nullable()->constrained('employee_pay_rates')->nullOnDelete();
            $table->string('pay_type', 20);
            $table->decimal('rate', 12, 2);                 // snapshot of the historical rate
            $table->decimal('overtime_multiplier', 5, 2)->default(1.5);
            $table->decimal('regular_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->unsignedSmallInteger('days_worked')->default(0);
            $table->decimal('base_pay', 14, 2)->default(0);
            $table->decimal('overtime_pay', 14, 2)->default(0);
            $table->decimal('bonuses', 14, 2)->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('advances', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('remaining_payable', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid, partially_paid, paid, overdue
            $table->json('deduction_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('employee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('salary'); // salary, advance, bonus, reimbursement
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('service_job_id')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $table->string('status', 20)->default('open')->index(); // open, in_progress, done, cancelled
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['employee_tasks', 'employee_payments', 'payroll_entries', 'payroll_periods', 'attendance_records'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
