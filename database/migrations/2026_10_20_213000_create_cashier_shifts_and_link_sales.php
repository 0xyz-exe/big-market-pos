<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('open_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->decimal('opening_cash', 14, 2);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('variance', 14, 2)->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['branch_id', 'open_user_id']);
            $table->index(['branch_id', 'status', 'opened_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('cashier_shift_id')->nullable()->after('cashier_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->index(['cashier_shift_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['cashier_shift_id']);
            $table->dropIndex(['cashier_shift_id', 'completed_at']);
            $table->dropColumn('cashier_shift_id');
        });

        Schema::dropIfExists('cashier_shifts');
    }
};
