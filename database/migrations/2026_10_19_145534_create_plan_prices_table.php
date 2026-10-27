<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A plan can be sold several ways: one-time (N days), monthly, or yearly.
     */
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 20);
            $table->unsignedSmallInteger('duration_days')->nullable();
            // Whole rupiah; IDR has no minor unit in practice.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['plan_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
