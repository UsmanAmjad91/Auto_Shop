<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customisable repair statuses (admins may add / rename / reorder).
        Schema::create('vehicle_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('color', 20)->default('secondary');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vin', 17)->unique();
            $table->string('stock_number', 30)->unique();
            $table->string('make', 60)->index();
            $table->string('model', 60)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('trim', 100)->nullable();
            $table->string('engine', 120)->nullable();
            $table->string('transmission', 60)->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('body_type', 20)->default('Other')->index();
            $table->string('drive_type', 30)->nullable();
            $table->string('exterior_color', 40)->nullable();
            $table->string('interior_color', 40)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('license_plate', 20)->nullable()->index();
            $table->string('registration_number', 60)->nullable();
            $table->date('registration_expiry')->nullable();
            $table->string('registration_state', 60)->nullable();
            $table->date('purchase_date')->nullable()->index();
            $table->string('purchase_source', 60)->nullable(); // auction, trade-in, private, dealer, other
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // owner for client vehicles
            $table->string('ownership_type', 20)->default('company')->index(); // company, customer, consignment, other
            $table->string('condition', 20)->nullable(); // excellent, good, fair, poor, salvage
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->string('repair_status', 60)->default('not_started')->index();
            $table->string('inventory_status', 20)->default('available')->index(); // available, in_repair, reserved, sold, delivered, returned, archived
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid, partial, paid, pending, refunded
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Money (decimal). Cost columns are cached aggregates recalculated by VehicleCostService.
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('parts_cost', 14, 2)->default(0);
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('interior_cost', 14, 2)->default(0);
            $table->decimal('detailing_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);
            $table->decimal('total_investment', 14, 2)->default(0)->index();
            $table->decimal('asking_price', 14, 2)->nullable()->index();
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->date('sold_at')->nullable();
            $table->foreignId('sold_to_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sale_invoice_id')->nullable(); // FK added after invoices table exists

            $table->json('custom_fields')->nullable();
            $table->json('decoded_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50)->default('other'); // title, registration, inspection, bill_of_sale, insurance, other
            $table->string('title');
            $table->string('original_name');
            $table->string('path'); // stored on private disk
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vehicle_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('field', 30)->default('repair_status'); // repair_status, inventory_status
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60);
            $table->text('notes')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Manual expenses only (transport, registration, auction fees...). Job costs come from service jobs.
        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50); // transport, registration, auction_fee, inspection, fuel, other
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('inspection_date');
            $table->string('type', 30)->default('intake'); // intake, pre_sale, quality, other
            $table->json('items'); // [{name, status: pass|fail|na, notes}]
            $table->string('result', 20)->default('pending'); // pass, fail, pending
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['vehicle_inspections', 'vehicle_expenses', 'vehicle_status_histories', 'vehicle_documents',
            'vehicle_images', 'vehicles', 'vehicle_statuses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
