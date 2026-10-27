<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TemplateCatalog;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(TemplateCatalog $catalog): View
    {
        return view('dashboard.templates.index', [
            'templates' => $catalog->publishedTemplates(),
            'categories' => $catalog->categories(),
        ]);
    }
}
