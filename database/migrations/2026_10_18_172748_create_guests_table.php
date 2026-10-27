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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('number_of_guests')->default(1);
            $table->string('table_name', 60)->nullable();
            $table->string('rsvp_status', 20)->default('pending');
            $table->string('invitation_status', 20)->default('not_sent');
            $table->text('notes')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invitation_id', 'name']);
            $table->index(['invitation_id', 'phone']);
            $table->index(['invitation_id', 'rsvp_status']);
            $table->index(['invitation_id', 'guest_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
