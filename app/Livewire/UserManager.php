<?php

namespace App\Livewire;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin user list (rules.md section 64): search and filter; actions live on the user page.
 */
class UserManager extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $role = '';

    public function mount(): void
    {
        Gate::authorize('users.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $search = trim($this->search);
        $status = UserStatus::tryFrom($this->status);
        $role = RoleSlug::tryFrom($this->role);

        return view('livewire.user-manager', [
            'users' => User::query()
                ->with(['roles:id,slug', 'subscriptions' => fn ($query) => $query->current()->with('plan:id,name')])
                ->withCount('invitations')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($role, fn ($query) => $query->withRole($role))
                ->latest()
                ->paginate(20),
            'statuses' => UserStatus::cases(),
            'roles' => RoleSlug::cases(),
        ]);
    }
}
