<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Background music of an invitation: {enabled, source: library|upload, track (ulid), upload (url), start_at, loop}.
     */
    public function up(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->json('music')->nullable()->after('decoration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->dropColumn('music');
        });
    }
};
