<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients can connect their own WhatsApp number. Providers without a user belong to the platform.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_providers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('ulid')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->string('sender', 20)->default('platform')->after('status');
        });

        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->foreignId('whatsapp_provider_id')->nullable()->after('provider')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_provider_id');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('sender');
        });

        Schema::table('whatsapp_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
