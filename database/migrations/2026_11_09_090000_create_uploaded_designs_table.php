<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_designs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->string('directory');
            $table->json('sections');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_designs');
    }
};
