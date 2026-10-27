<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Template\TemplateDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TemplateBuilderController extends Controller
{
    public function show(InvitationTemplate $template): View
    {
        Gate::authorize('templates.manage');

        return view('admin.templates.builder', ['template' => $template]);
    }

    /**
     * The builder iframe: the admin's unsaved draft, with or without sample content.
     */
    public function preview(Request $request, InvitationTemplate $template, TemplateDraft $draft, InvitationRenderer $renderer): View
    {
        Gate::authorize('templates.manage');

        return view('invitation.show', [
            'invitation' => $renderer->forTemplatePreview(
                $draft->previewTemplate($template, $request->user()),
                withSamples: $request->query('content') !== 'default_configuration',
            ),
            'showBranding' => false,
        ]);
    }
}
