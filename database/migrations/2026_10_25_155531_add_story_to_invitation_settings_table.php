<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram Story image settings: {layout, photo, venue, link, headline, message, guest_button}.
     */
    public function up(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->json('story')->nullable()->after('music');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->dropColumn('story');
        });
    }
};
