<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Admin audit log (rules.md section 60): who did what, on which entity, from where and when.
 */
class AuditLogViewer extends Component
{
    use WithPagination;

    /** Action prefixes ("template.published" → "template") offered as a filter, with their labels. */
    public const GROUPS = [
        'auth' => 'Sign-in & registration',
        'user' => 'User accounts',
        'invitation' => 'Invitations',
        'guest' => 'Guests',
        'template' => 'Templates',
        'illustration' => 'Illustrations',
        'music' => 'Music',
        'whatsapp' => 'WhatsApp',
        'order' => 'Orders',
        'payment' => 'Payments',
        'subscription' => 'Subscriptions',
        'settings' => 'System settings',
        'cms' => 'Site content',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('audit-logs.view');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $like = '%'.addcslashes($search, '%_\\').'%';

        return view('livewire.audit-log-viewer', [
            'logs' => AuditLog::query()
                ->with(['user' => fn ($query) => $query->withTrashed()->select(['id', 'ulid', 'name', 'email', 'deleted_at'])])
                ->when(array_key_exists($this->group, self::GROUPS), fn ($query) => $query->where('action', 'like', $this->group.'.%'))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('action', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('user', fn ($query) => $query->withTrashed()->where(fn ($query) => $query->where('email', 'like', $like)->orWhere('name', 'like', $like)))))
                ->when($this->date($this->from), fn ($query, CarbonImmutable $from) => $query->where('created_at', '>=', $from->startOfDay()))
                ->when($this->date($this->to), fn ($query, CarbonImmutable $to) => $query->where('created_at', '<=', $to->endOfDay()))
                ->latest('created_at')
                ->latest('id')
                ->paginate(25),
            'groups' => self::GROUPS,
        ]);
    }

    private function date(string $value): ?CarbonImmutable
    {
        try {
            return $value === '' ? null : CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
