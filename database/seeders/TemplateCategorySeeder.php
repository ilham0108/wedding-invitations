<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\TemplateCategory;
use Illuminate\Database\Seeder;

class TemplateCategorySeeder extends Seeder
{
    /**
     * One category per event type, so the creation wizard can match templates to events.
     */
    public function run(): void
    {
        foreach (EventType::cases() as $sortOrder => $eventType) {
            TemplateCategory::query()->updateOrCreate(
                ['slug' => $eventType->value],
                ['name' => $eventType->label(), 'event_type' => $eventType, 'icon' => $eventType->icon(), 'sort_order' => $sortOrder, 'is_active' => true],
            );
        }
    }
}
