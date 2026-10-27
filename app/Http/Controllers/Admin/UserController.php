<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.manage');

        return view('admin.users.index');
    }

    public function show(User $user): View
    {
        Gate::authorize('users.manage');

        return view('admin.users.show', ['user' => $user]);
    }
}
