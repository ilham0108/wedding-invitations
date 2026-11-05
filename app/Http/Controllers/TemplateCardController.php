<?php

namespace App\Http\Controllers;

use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The template's real cover as a still page, shown in a frame on template cards (x-template-card).
 * Same access as the preview: published templates are public, drafts are only for template admins.
 */
class TemplateCardController extends Controller
{
    /** Card URLs carry the template's version, so browsers may keep the page for a while. */
    private const MAX_AGE = 3600;

    public function __invoke(Request $request, InvitationTemplate $template, InvitationRenderer $renderer): Response
    {
        abort_unless($template->isPublished() || $request->user()?->can('templates.manage'), 404);

        return response()
            ->view('invitation.show', [
                'invitation' => $renderer->forTemplatePreview($template, coverOnly: true),
                'embedded' => true,
                'static' => true,
                'showBranding' => false,
            ])
            ->header('Cache-Control', 'private, max-age='.self::MAX_AGE);
    }
}
