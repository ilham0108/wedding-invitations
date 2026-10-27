<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-curated background music clients pick for their invitation (rules.md section 30).
     */
    public function up(): void
    {
        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('title', 120);
            $table->string('artist', 120)->nullable();
            // null disk = bundled file shipped in public/; otherwise a Storage disk.
            $table->string('disk', 20)->nullable();
            $table->string('path', 255);
            $table->unsignedInteger('size')->default(0);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_tracks');
    }
};
