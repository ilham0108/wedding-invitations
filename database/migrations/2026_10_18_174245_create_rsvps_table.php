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
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('attendance', 20);
            $table->unsignedSmallInteger('number_of_guests')->default(0);
            $table->string('meal_preference', 60)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['invitation_id', 'attendance']);
            $table->index(['invitation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};
