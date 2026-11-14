<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The link in the verification email. It works in any browser, signed in or not, and whoever is signed in:
 * people often open it on another device, or while signed in to another account. The signed, expiring URL
 * with the public ULID and the hash of the address is the proof; the session plays no part.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->where('ulid', $id)->first();

        abort_if($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $current = $request->user();

        if ($current?->is($user)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // Signed in to another account: that session stays as it is.
        if ($current !== null) {
            return to_route('dashboard')->with('success', __('The email address has been verified. Sign in with that account to use it.'));
        }

        return to_route('login')->with('status', __('Your email address has been verified. Please sign in.'));
    }
}
