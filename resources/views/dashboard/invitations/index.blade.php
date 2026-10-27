<x-layouts.app area="dashboard" :title="__('My Invitations')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create Invitation') }}</x-ui.button>
    </x-slot:actions>

    @if ($invitations->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-regular fa-envelope-open" :title="__('You haven\'t created an invitation yet.')"
                :description="__('Choose a template and have your first invitation ready in minutes.')">
                <x-slot:action>
                    <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create your first invitation') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="space-y-3">
            @foreach ($invitations as $invitation)
                <x-ui.card padding="p-4 sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate font-medium text-ink">{{ $invitation->title }}</h2>
                                <x-ui.badge :variant="$invitation->status->badgeVariant()">{{ $invitation->status->label() }}</x-ui.badge>
                                @if (in_array($invitation->ulid, $offline, true))
                                    <a href="{{ route('dashboard.subscription.index') }}" title="{{ __('Guests cannot open this invitation on your current plan.') }}">
                                        <x-ui.badge variant="danger"><i class="fa-solid fa-plug-circle-xmark mr-1" aria-hidden="true"></i>{{ __('Offline') }}</x-ui.badge>
                                    </a>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-muted">
                                {{ $invitation->template->name }} · {{ $invitation->event_type->label() }} ·
                                {{ __('Updated :time', ['time' => $invitation->updated_at->diffForHumans()]) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button :href="route('dashboard.invitations.edit', $invitation)" size="sm" icon="fa-solid fa-pen">{{ __('Edit') }}</x-ui.button>
                            <x-ui.button :href="route('dashboard.guests.index', ['invitation' => $invitation->ulid])" variant="secondary" size="sm" icon="fa-solid fa-user-group">{{ __('Guests') }}</x-ui.button>
                            <x-ui.button :href="route('dashboard.invitations.preview', $invitation)" variant="secondary" size="sm" icon="fa-regular fa-eye">{{ __('Preview') }}</x-ui.button>
                            <x-ui.button :href="route('dashboard.invitations.story', $invitation)" variant="secondary" size="sm" icon="fa-brands fa-instagram">{{ __('IG Story') }}</x-ui.button>
                            @if ($invitation->isPublished())
                                <x-ui.button :href="route('invitations.public', $invitation->slug)" variant="secondary" size="sm" icon="fa-solid fa-arrow-up-right-from-square" target="_blank">{{ __('Open') }}</x-ui.button>
                            @endif
                            <form method="POST" action="{{ route('dashboard.invitations.destroy', $invitation) }}"
                                x-data x-on:submit="if (! confirm(@js(__('Delete this invitation?')))) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost" size="sm" icon="fa-regular fa-trash-can" aria-label="{{ __('Delete') }}" />
                            </form>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <div class="mt-6">{{ $invitations->links() }}</div>
    @endif
</x-layouts.app>
