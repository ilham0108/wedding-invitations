<?php

use App\Models\InvitationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations keep a copy of their template's theme and decoration, so later
 * template edits only affect invitations created afterwards.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->json('template_theme')->nullable()->after('invitation_id');
            $table->json('decoration')->nullable()->after('theme');
        });

        InvitationSetting::query()
            ->with(['invitation' => fn ($query) => $query->withTrashed()->with(['template' => fn ($query) => $query->withTrashed()])])
            ->chunkById(200, function ($settings): void {
                foreach ($settings as $setting) {
                    $template = $setting->invitation?->template;

                    $setting->forceFill([
                        'template_theme' => $template?->theme ?? [],
                        'decoration' => $template?->decoration ?? [],
                    ])->saveQuietly();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitation_settings', function (Blueprint $table) {
            $table->dropColumn(['template_theme', 'decoration']);
        });
    }
};
