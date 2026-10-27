<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20)->index(); // repair, interior, cleaning
            $table->string('name');
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('default_cost', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'name']);
        });

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 30)->unique();
            $table->string('category', 20)->index(); // repair, interior, cleaning
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, scheduled, in_progress, on_hold, completed, quality_checked, delivered, cancelled
            $table->string('priority', 10)->default('normal');
            $table->date('scheduled_date')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('completion_date')->nullable();
            $table->unsignedInteger('odometer_in')->nullable();
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('material_cost', 14, 2)->default(0);
            $table->decimal('parts_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->decimal('customer_charge', 14, 2)->default(0);
            $table->boolean('is_billable')->default(true);
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('invoice_id')->nullable()->index(); // FK added below
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20); // service, labor, part, material
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_job_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('technician');
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['service_job_id', 'employee_id']);
        });

        Schema::create('service_job_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 10); // before, after, progress
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->string('type', 20)->index(); // vehicle_sale, repair, parts_sale, interior, detailing, combined
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('draft')->index(); // draft, issued, partially_paid, paid, overdue, void, refunded
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid, partial, paid, pending, refunded
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 10)->default('fixed'); // fixed, percent
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('cost_total', 14, 2)->default(0); // for gross margin reports
            // snapshots so printed invoices never change if the customer record changes
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20); // vehicle, part, labor, interior, detailing, service_job, other
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->boolean('taxable')->default(true);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->boolean('stock_deducted')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('payment')->index(); // payment, deposit, refund, financing_settlement
            $table->date('payment_date')->index();
            $table->decimal('amount', 14, 2); // positive; refunds stored positive with type=refund
            $table->string('method', 30); // cash, credit_card, debit_card, bank_transfer, check, financing, other
            $table->string('reference')->nullable(); // last 4 / cheque # / transfer ref - never full card data
            $table->string('status', 20)->default('completed')->index(); // completed, reversed
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->foreignId('refund_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('financing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('lender_name');
            $table->string('lender_contact')->nullable();
            $table->string('loan_reference')->nullable();
            $table->decimal('financed_amount', 14, 2);
            $table->decimal('down_payment', 14, 2)->default(0);
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, settled, cancelled
            $table->date('approved_date')->nullable();
            $table->date('settled_date')->nullable();
            $table->foreignId('settlement_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('sale_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
        Schema::table('employee_tasks', function (Blueprint $table) {
            $table->foreign('service_job_id')->references('id')->on('service_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_tasks', fn (Blueprint $t) => $t->dropForeign(['service_job_id']));
        Schema::table('service_jobs', fn (Blueprint $t) => $t->dropForeign(['invoice_id']));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropForeign(['sale_invoice_id']));
        foreach (['financing_records', 'payment_allocations', 'payments', 'invoice_items', 'invoices',
            'service_job_images', 'service_job_employees', 'service_job_items', 'service_jobs', 'service_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
