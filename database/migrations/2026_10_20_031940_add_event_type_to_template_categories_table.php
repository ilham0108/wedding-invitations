<?php

use App\Enums\EventType;
use App\Models\TemplateCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories used to be matched to event types by slug. An explicit event type lets
 * several categories (e.g. "Islamic Wedding", "Modern Wedding") serve one event type.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('template_categories', function (Blueprint $table) {
            $table->string('event_type', 30)->default(EventType::Other->value)->after('slug')->index();
        });

        TemplateCategory::query()->each(function (TemplateCategory $category): void {
            $category->forceFill(['event_type' => EventType::tryFrom($category->slug) ?? EventType::Other])->saveQuietly();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_categories', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn('event_type');
        });
    }
};
