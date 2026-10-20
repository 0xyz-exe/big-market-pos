<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_percent');
            $table->string('discount_reason', 255)->nullable()->after('discount_amount');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('discount_amount', 14, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn('discount_amount');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['discount_percent', 'discount_amount', 'discount_reason']);
        });
    }
};
