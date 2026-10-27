<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A template points at a design pack (resources/views/designs/{design}); invitations
 * keep a copy of it, like the theme. Existing rows use the built-in "classic" design.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitation_templates', function (Blueprint $table) {
            $table->string('design', 50)->default('classic')->after('schema_version');
        });

        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->string('design', 50)->default('classic')->after('invitation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_templates', function (Blueprint $table) {
            $table->dropColumn('design');
        });

        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->dropColumn('design');
        });
    }
};
