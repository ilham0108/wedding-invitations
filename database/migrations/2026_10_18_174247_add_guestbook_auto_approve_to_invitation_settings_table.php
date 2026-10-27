<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->boolean('guestbook_auto_approve')->default(false)->after('allow_search_indexing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->dropColumn('guestbook_auto_approve');
        });
    }
};
