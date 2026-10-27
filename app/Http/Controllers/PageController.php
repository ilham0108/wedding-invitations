<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * Public static pages from the admin CMS (terms, privacy, …). Drafts are not found.
 */
class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('marketing.page', ['page' => $page]);
    }
}
