<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per provider API call (rules.md section 78: WhatsApp API request/result).
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->unsignedTinyInteger('attempt');
            $table->boolean('success');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('error', 500)->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
