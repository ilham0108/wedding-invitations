<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FontController extends Controller
{
    public function index(): View
    {
        Gate::authorize('fonts.manage');

        return view('admin.fonts.index');
    }
}
