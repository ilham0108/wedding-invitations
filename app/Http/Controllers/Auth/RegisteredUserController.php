<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\GoogleLoginService;
use App\Services\SystemSettings;
use App\Services\UserRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(SystemSettings $settings, GoogleLoginService $google): View
    {
        return view('auth.register', [
            'registrationOpen' => $settings->registrationOpen(),
            'googleEnabled' => $google->enabled(),
        ]);
    }

    /**
     * Handle an incoming registration request; refused while the admin has closed registration.
     */
    public function store(RegisterRequest $request, UserRegistrationService $registration, SystemSettings $settings): RedirectResponse
    {
        if (! $settings->registrationOpen()) {
            return to_route('register');
        }

        $user = $registration->registerClient($request->safe()->only(['name', 'email', 'password']));

        Auth::login($user);

        return to_route('dashboard');
    }
}
