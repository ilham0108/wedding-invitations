<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class TemplateController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Template gallery, filtered by category (?category=slug) and paged on the server.
     * Templates come from the cached catalog, so the page is sliced from that list.
     */
    public function index(Request $request, TemplateCatalog $catalog): View
    {
        $categories = $catalog->categories();
        $category = $categories->firstWhere('slug', (string) $request->query('category'))?->slug;

        $templates = $catalog->publishedTemplates()
            ->when($category, fn ($templates) => $templates->filter(fn ($template): bool => $template->category->slug === $category))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('dashboard.templates.index', [
            'templates' => new LengthAwarePaginator(
                $templates->forPage($page, self::PER_PAGE)->values(),
                $templates->count(),
                self::PER_PAGE,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'categories' => $categories,
            'category' => $category,
        ]);
    }
}
