<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IllustrationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('illustrations.manage');

        return view('admin.illustrations.index');
    }
}
