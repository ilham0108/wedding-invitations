<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\ThemeResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Instagram Story page; the InvitationStory component draws, saves and downloads the image.
 */
class InvitationStoryController extends Controller
{
    public function __invoke(Invitation $invitation, ThemeResolver $themes, DesignRegistry $designs): View
    {
        Gate::authorize('update', $invitation);

        $settings = $invitation->settings;
        $theme = $themes->resolve($settings?->template_theme ?? [], $settings?->theme ?? []);

        return view('dashboard.invitations.story', [
            'invitation' => $invitation,
            // The theme's fonts, so the canvas can draw with them.
            'fontStylesheetUrl' => $themes->fontStylesheetUrl($theme, $designs->fonts($designs->resolve($settings?->design))),
        ]);
    }
}
