<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fonts installed by an admin, on top of the built-in list in config/invitation.php (fonts):
     * either a Bunny Fonts family or uploaded font files.
     */
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // The CSS family name themes refer to (theme.font_heading / font_body).
            $table->string('name', 60)->unique();
            $table->string('category', 20);
            $table->string('source', 10);
            // Bunny Fonts "family" parameter, e.g. "cormorant-infant:400,500,600" (source = bunny).
            $table->string('bunny_family', 160)->nullable();
            $table->string('fallback', 80);
            // Hidden fonts leave the pickers but keep working where they are already used.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('font_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('font_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('format', 10);
            $table->unsignedSmallInteger('weight')->default(400);
            $table->string('style', 10)->default('normal');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('font_files');
        Schema::dropIfExists('fonts');
    }
};
