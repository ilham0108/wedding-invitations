<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, AccountDeletionService $accounts): View
    {
        return view('dashboard.settings.edit', [
            'user' => $request->user(),
            // Real counts for the deletion warning, so the user sees exactly what will be lost.
            'deletion' => $accounts->summary($request->user()),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account and its invitations for good (AccountDeletionService).
     * The user ticks a box naming what is lost; accounts with a password also re-enter it.
     */
    public function destroy(Request $request, AccountDeletionService $accounts): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => [Rule::requiredIf($request->user()->hasPassword()), 'current_password'],
            'confirm_permanent' => ['accepted'],
        ], [
            'confirm_permanent.accepted' => __('Tick the box to confirm that you understand this cannot be undone.'),
        ]);

        $user = $request->user();

        Auth::logout();

        $accounts->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
