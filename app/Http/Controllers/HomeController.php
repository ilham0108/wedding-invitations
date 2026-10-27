<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\SiteContentService;
use App\Services\TemplateCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(TemplateCatalog $catalog, SiteContentService $content): View
    {
        return view('marketing.home', [
            // Copy edited in the admin CMS (rules.md section 52).
            'content' => $content->all(),
            'templates' => $catalog->publishedTemplates(),
            'categories' => $catalog->categories(),
            // Prices come from the database (rules.md "Pricing": never hard-coded).
            'plans' => Plan::query()->active()->ordered()->with('activePrices')->get(),
            'features' => config('plans.features'),
        ]);
    }
}
