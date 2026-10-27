<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('cms.manage');

        return view('admin.content.index');
    }
}
