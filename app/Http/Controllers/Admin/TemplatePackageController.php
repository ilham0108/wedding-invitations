<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvitationTemplate;
use App\Services\Template\TemplatePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download a template as a .zip (import happens in the TemplateManager component).
 */
class TemplatePackageController extends Controller
{
    public function export(Request $request, InvitationTemplate $template, TemplatePackage $packages): BinaryFileResponse
    {
        Gate::authorize('templates.manage');

        return response()
            ->download($packages->export($template, $request->user()), $packages->fileName($template), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
