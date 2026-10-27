<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment attempt at a gateway (an order can be retried).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            // Our id sent to the gateway, and the gateway's own id.
            $table->string('external_id', 80)->unique();
            $table->string('gateway_reference', 120)->nullable()->index();
            $table->string('checkout_url', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->nullable();
            $table->string('method', 60)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
