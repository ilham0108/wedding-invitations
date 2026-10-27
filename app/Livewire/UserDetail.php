<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin user page (rules.md section 64): profile, subscription, invitations, recent activity,
 * and suspend / activate / delete.
 */
class UserDetail extends Component
{
    use Notifies;

    #[Locked]
    public string $userUlid = '';

    public string $reason = '';

    public function mount(User $user): void
    {
        Gate::authorize('users.manage');

        $this->userUlid = $user->ulid;
    }

    public function suspend(UserManagementService $users): void
    {
        Gate::authorize('users.suspend');

        $this->validate(['reason' => ['nullable', 'string', 'max:500']]);

        if ($this->attempt(fn () => $users->suspend($this->user(), Auth::user(), $this->reason))) {
            $this->reason = '';
            $this->notify('success', __('Account suspended. The user is signed out and their invitations are offline.'));
        }
    }

    public function activate(UserManagementService $users): void
    {
        Gate::authorize('users.suspend');

        $users->activate($this->user(), Auth::user());
        $this->notify('success', __('Account activated.'));
    }

    public function delete(UserManagementService $users): void
    {
        Gate::authorize('users.manage');

        if ($this->attempt(fn () => $users->delete($this->user(), Auth::user()))) {
            session()->flash('success', __('Account deleted.'));
            $this->redirectRoute('admin.users.index', navigate: false);
        }
    }

    public function render(): View
    {
        $user = $this->user()->load([
            'roles:id,slug',
            'subscriptions' => fn ($query) => $query->with('plan:id,name')->latest('starts_at'),
            'invitations' => fn ($query) => $query->with('template:id,name')->withCount('guests')->latest(),
        ]);

        return view('livewire.user-detail', [
            'user' => $user,
            'current' => $user->subscriptions->first(fn ($subscription): bool => $subscription->isCurrent()),
            'activity' => AuditLog::query()->where('user_id', $user->getKey())->latest('created_at')->limit(10)->get(['action', 'metadata', 'ip_address', 'created_at']),
            'isSelf' => $user->is(Auth::user()),
        ]);
    }

    /**
     * Run an action; guard failures (own account, super admin) become an error toast.
     */
    private function attempt(callable $action): bool
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->notify('error', collect($exception->errors())->flatten()->first());

            return false;
        }

        return true;
    }

    private function user(): User
    {
        return User::query()->where('ulid', $this->userUlid)->firstOrFail();
    }
}
