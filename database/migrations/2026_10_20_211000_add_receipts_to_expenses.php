<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name', 255)->nullable();
            $table->string('receipt_mime_type', 127)->nullable();
            $table->unsignedBigInteger('receipt_size_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn([
                'receipt_path',
                'receipt_original_name',
                'receipt_mime_type',
                'receipt_size_bytes',
            ]);
        });
    }
};
