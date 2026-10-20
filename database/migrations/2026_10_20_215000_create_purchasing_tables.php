<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_products', function (Blueprint $table): void {
            $table->decimal('purchase_cost', 14, 2)->nullable()->after('selling_price');
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['is_active', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('order_number', 80)->unique();
            $table->string('status', 24)->default('ordered');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestampTz('ordered_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->index(['branch_id', 'status', 'ordered_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_sku', 64);
            $table->string('product_name');
            $table->decimal('quantity_ordered', 14, 3);
            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestampsTz();
            $table->unique(['purchase_order_id', 'product_id']);
            $table->index(['purchase_order_id', 'quantity_received']);
        });

        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 80)->unique();
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->timestampTz('received_at');
            $table->timestampsTz();
            $table->unique(['branch_id', 'idempotency_key']);
            $table->index(['branch_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestampsTz();
            $table->unique(['purchase_receipt_id', 'purchase_order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');

        Schema::table('branch_products', function (Blueprint $table): void {
            $table->dropColumn('purchase_cost');
        });
    }
};
