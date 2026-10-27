<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        Gate::authorize('orders.manage');

        return view('admin.orders.index', ['tab' => 'orders', 'title' => __('Orders')]);
    }

    public function payments(): View
    {
        Gate::authorize('payments.manage');

        return view('admin.orders.index', ['tab' => 'payments', 'title' => __('Payments')]);
    }
}
