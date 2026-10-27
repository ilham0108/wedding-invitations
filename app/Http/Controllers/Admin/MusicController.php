<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MusicController extends Controller
{
    public function index(): View
    {
        Gate::authorize('music.manage');

        return view('admin.music.index');
    }
}
