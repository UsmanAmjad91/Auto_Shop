<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // warehouse
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number', 30)->unique(); // internal Part ID
            $table->string('name');
            $table->string('oem_number', 60)->nullable()->index();
            $table->string('sku', 60)->nullable()->unique();
            $table->string('barcode', 80)->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->string('manufacturer', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('purchase_cost', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->integer('quantity')->default(0);           // on hand across all locations
            $table->integer('quantity_reserved')->default(0);  // reserved for jobs/quotes
            $table->integer('quantity_damaged')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->foreignId('default_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('shelf', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->string('purchase_invoice')->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('condition', 20)->default('new'); // new, used, refurbished
            $table->string('stock_status', 20)->default('out_of_stock')->index(); // available, low_stock, out_of_stock
            $table->boolean('allow_negative_stock')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('part_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->string('shelf', 30)->nullable();
            $table->string('bin', 30)->nullable();
            $table->timestamps();
            $table->unique(['part_id', 'location_id']);
        });

        Schema::create('part_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('part_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('make', 60);
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->timestamps();
            $table->index(['make', 'model']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, ordered, partially_received, received, cancelled
            $table->string('supplier_invoice_number')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Immutable ledger of every stock change.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->string('type', 30)->index(); // purchase, add, remove, adjust, reserve, unreserve, job_issue, job_return, sale, sale_return, return_to_supplier, damaged, transfer_in, transfer_out
            $table->integer('quantity'); // signed change to on-hand (0 for reservations)
            $table->integer('reserved_change')->default(0);
            $table->integer('balance_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->nullableMorphs('reference'); // ServiceJobItem, InvoiceItem, PurchaseOrder...
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['part_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['stock_movements', 'supplier_payments', 'purchase_order_items', 'purchase_orders',
            'part_compatibilities', 'part_images', 'part_stocks', 'parts', 'stock_locations', 'part_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
