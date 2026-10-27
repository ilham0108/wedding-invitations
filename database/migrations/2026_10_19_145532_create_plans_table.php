<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plans are fully database-driven (rules.md "Do not hard-code prices / limits").
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->json('features');
            // The plan everyone gets without paying; exactly one should be marked.
            $table->boolean('is_free')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            // Higher rank = more generous; used to block accidental downgrades.
            $table->unsignedSmallInteger('rank')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
