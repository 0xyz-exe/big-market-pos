<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit_of_measure', 32)->default('pcs');
            $table->decimal('purchase_cost', 14, 2)->default(0);
            $table->decimal('default_selling_price', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['is_active', 'name']);
        });

        Schema::create('branch_products', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('selling_price', 14, 2);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestampsTz();
            $table->primary(['branch_id', 'product_id']);
            $table->index(['branch_id', 'is_available']);
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->timestampsTz();
            $table->unique(['branch_id', 'product_id']);
            $table->index(['branch_id', 'quantity_on_hand']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity_delta', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason', 255);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('branch_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
