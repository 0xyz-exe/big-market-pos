<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('transaction_number', 64)->unique();
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->string('status', 24)->default('completed');
            $table->string('payment_method', 24)->default('cash');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('amount_received', 14, 2);
            $table->decimal('change_due', 14, 2)->default(0);
            $table->timestampTz('completed_at');
            $table->timestampsTz();
            $table->unique(['branch_id', 'cashier_id', 'idempotency_key']);
            $table->index(['branch_id', 'completed_at']);
            $table->index(['branch_id', 'cashier_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
