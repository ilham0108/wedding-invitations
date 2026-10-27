<x-layouts.app area="dashboard" :title="__('WhatsApp')">
    @if ($selected === null)
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-brands fa-whatsapp" :title="__('No campaigns yet.')"
                :description="__('Create an invitation and add guests before sending WhatsApp messages.')">
                <x-slot:action>
                    <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create Invitation') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard.whatsapp.index') }}" class="flex items-center gap-2">
                <label for="invitation" class="text-sm text-muted">{{ __('Invitation') }}</label>
                <select id="invitation" name="invitation" onchange="this.form.submit()" class="rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    @foreach ($invitations as $invitation)
                        <option value="{{ $invitation->ulid }}" @selected($invitation->ulid === $selected->ulid)>{{ $invitation->title }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>

        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-white px-5 py-4">
            <span @class(['flex h-10 w-10 items-center justify-center rounded-full', 'bg-emerald-50 text-emerald-600' => $ownConnection, 'bg-paper text-muted' => ! $ownConnection])>
                <i class="fa-brands fa-whatsapp text-lg" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-medium text-ink">{{ __('My WhatsApp number') }}</p>
                <p class="text-xs text-muted">
                    {{ $ownConnection
                        ? __('Connected: :name. Campaigns can be sent from your own number.', ['name' => $ownConnection->name])
                        : __('Not connected. Connect your number so guests receive invitations from you.') }}
                </p>
            </div>
            <x-ui.button :href="route('dashboard.whatsapp.connection')" :variant="$ownConnection ? 'secondary' : 'primary'" size="sm" icon="fa-solid fa-link">
                {{ $ownConnection ? __('Manage') : __('Connect number') }}
            </x-ui.button>
        </div>

        @if (! $configured && ! $ownConnection)
            <div class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                <i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ __('WhatsApp provider is not configured.') }}
                {{ __('Connect your own number or ask the administrator to set up the platform number.') }}
            </div>
        @endif

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Platform messages this month')" :value="Number::format($usage['used'])" icon="fa-regular fa-paper-plane" />
            <x-ui.stat-card :label="__('Monthly limit')" :value="$usage['limit'] === null ? __('Unlimited') : Number::format($usage['limit'])" icon="fa-solid fa-gauge" />
            <x-ui.stat-card :label="__('Remaining')" :value="$usage['remaining'] === null ? '—' : Number::format($usage['remaining'])" icon="fa-regular fa-hourglass" />
        </div>

        <x-ui.card :title="__('Campaigns')" padding="p-0">
            <x-slot:actions>
                <div class="flex gap-2">
                    <x-ui.button :href="route('dashboard.whatsapp.templates')" variant="secondary" size="sm" icon="fa-regular fa-message">{{ __('Templates') }}</x-ui.button>
                    <x-ui.button :href="route('dashboard.whatsapp.create', ['invitation' => $selected->ulid])" size="sm" icon="fa-solid fa-plus">{{ __('New campaign') }}</x-ui.button>
                </div>
            </x-slot:actions>

            @if ($campaigns->isEmpty())
                <x-ui.empty-state icon="fa-brands fa-whatsapp" :title="__('No campaigns yet.')" :description="__('Send your invitation to every guest with their personal link in one go.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($campaigns as $campaign)
                        <li>
                            <a href="{{ route('dashboard.whatsapp.campaigns.show', $campaign) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-paper/60">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-ink">{{ $campaign->name }}</p>
                                    <p class="text-xs text-muted">
                                        {{ trans_choice(':count recipient|:count recipients', $campaign->total_recipients) }} · {{ $campaign->sender->label() }} ·
                                        @if ($campaign->scheduled_at && $campaign->status->value === 'scheduled')
                                            {{ __('Scheduled for :time', ['time' => $campaign->scheduled_at->timezone($campaign->timezone)->translatedFormat('d M Y, H:i').' ('.$campaign->timezone.')']) }}
                                        @else
                                            {{ $campaign->created_at->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <x-ui.badge :variant="$campaign->status->badgeVariant()">{{ $campaign->status->label() }}</x-ui.badge>
                                <i class="fa-solid fa-chevron-right text-xs text-muted" aria-hidden="true"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-4 py-3">{{ $campaigns->links() }}</div>
            @endif
        </x-ui.card>
    @endif
</x-layouts.app>
