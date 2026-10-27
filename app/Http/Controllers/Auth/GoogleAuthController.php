<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SocialLoginException;
use App\Http\Controllers\Controller;
use App\Services\GoogleLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirect(GoogleLoginService $google): SymfonyRedirectResponse
    {
        abort_unless($google->enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Log in (or create the account for) the Google user coming back from the consent screen.
     */
    public function callback(Request $request, GoogleLoginService $google): RedirectResponse
    {
        abort_unless($google->enabled(), 404);

        try {
            $user = $google->resolveUser(Socialite::driver('google')->user());
        } catch (SocialLoginException $exception) {
            return to_route('login')->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return to_route('login')->with('error', __('Google sign-in failed. Please try again.'));
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        $home = $user->isSuperAdmin() ? route('admin.dashboard', absolute: false) : route('dashboard', absolute: false);

        return redirect()->intended($home);
    }
}
