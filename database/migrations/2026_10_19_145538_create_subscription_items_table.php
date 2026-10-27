<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature values frozen at purchase time, so editing a plan later never
     * changes what a customer already paid for.
     */
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 60);
            // JSON-encoded scalar: integer limit, null (unlimited), or boolean.
            $table->string('value', 60)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'feature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};
