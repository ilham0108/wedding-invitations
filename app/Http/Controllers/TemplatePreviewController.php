<?php

namespace App\Http\Controllers;

use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplatePreviewController extends Controller
{
    /**
     * Published templates are public; drafts are only visible to template admins.
     */
    public function __invoke(Request $request, InvitationTemplate $template, InvitationRenderer $renderer): View
    {
        $isPublished = $template->isPublished();

        abort_unless($isPublished || $request->user()?->can('templates.manage'), 404);

        return view('invitation.show', [
            'invitation' => $renderer->forTemplatePreview($template),
            'previewLabel' => $isPublished
                ? __('Template preview: :name', ['name' => $template->name])
                : __('Draft template preview: :name', ['name' => $template->name]),
            'useTemplateUrl' => $isPublished ? route('dashboard.invitations.create', ['template' => $template->slug]) : null,
        ]);
    }
}
