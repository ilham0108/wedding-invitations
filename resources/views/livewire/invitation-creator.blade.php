<div>
    {{-- Step indicator --}}
    <ol class="mb-8 flex items-center gap-2 text-sm">
        @foreach ([1 => __('Event type'), 2 => __('Template'), 3 => __('Details')] as $number => $label)
            <li class="flex items-center gap-2">
                <span @class([
                    'flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-ink text-white' => $step >= $number,
                    'bg-white text-muted ring-1 ring-line' => $step < $number,
                ])>{{ $number }}</span>
                <span @class(['font-medium text-ink' => $step === $number, 'text-muted' => $step !== $number])>{{ $label }}</span>
                @if (! $loop->last)
                    <span class="mx-1 h-px w-8 bg-line"></span>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Step 1: event type --}}
    @if ($step === 1)
        <h2 class="font-display text-2xl text-ink">{{ __('What are you celebrating?') }}</h2>
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($eventTypes as $type)
                <button type="button" wire:click="selectEventType('{{ $type->value }}')"
                    class="flex flex-col items-center gap-3 rounded-xl border border-line bg-white px-4 py-6 text-sm font-medium text-ink shadow-soft transition hover:border-ink/30">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-accent-soft text-accent"><i class="{{ $type->icon() }}" aria-hidden="true"></i></span>
                    {{ __($type->label()) }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Step 2: template --}}
    @if ($step === 2)
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl text-ink">{{ __('Choose a design') }}</h2>
                @if ($showingAllTemplates)
                    <p class="mt-1 text-sm text-muted">{{ __('No dedicated templates for this event type yet — every design works for any event.') }}</p>
                @endif
            </div>
            <x-ui.button type="button" variant="ghost" size="sm" wire:click="back" icon="fa-solid fa-arrow-left">{{ __('Back') }}</x-ui.button>
        </div>

        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <div wire:key="template-{{ $template->slug }}">
                    <x-template-card :template="$template" />
                    <div class="mt-2 flex gap-2">
                        <x-ui.button :href="route('templates.preview', $template)" variant="secondary" size="sm" class="flex-1" target="_blank">{{ __('Preview') }}</x-ui.button>
                        <x-ui.button type="button" size="sm" class="flex-1" wire:click="selectTemplate('{{ $template->slug }}')">{{ __('Use Template') }}</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Step 3: basic details --}}
    @if ($step === 3)
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card class="lg:col-span-2" :title="__('Basic information')" :description="__('You can change everything later in the editor.')">
                <form wire:submit="create" class="space-y-5">
                    <x-ui.field name="title" :label="__('Invitation title')" :hint="__('For example: John & Jane')">
                        <x-ui.input name="title" wire:model="title" maxlength="120" required autofocus />
                    </x-ui.field>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.field name="event_date" for="eventDate" :label="__('Event date (optional)')">
                            <x-ui.input name="event_date" id="eventDate" type="date" wire:model="eventDate" />
                        </x-ui.field>
                        <x-ui.field name="event_time" for="eventTime" :label="__('Start time (optional)')">
                            <x-ui.input name="event_time" id="eventTime" type="time" wire:model="eventTime" />
                        </x-ui.field>
                    </div>

                    <x-ui.field name="venue" :label="__('Venue (optional)')">
                        <x-ui.input name="venue" wire:model="venue" maxlength="160" />
                    </x-ui.field>

                    <x-ui.field name="timezone" :label="__('Event timezone')" :hint="__('The countdown uses this timezone.')">
                        <x-ui.select name="timezone" wire:model="timezone" :value="$timezone" :options="array_combine($timezones, $timezones)" />
                    </x-ui.field>

                    @error('template')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                    @error('plan')
                        <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                            {{ $message }}
                            <a href="{{ route('dashboard.subscription.index') }}" class="font-medium underline">{{ __('See plans') }}</a>
                        </div>
                    @enderror
                    @error('event_type')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <x-ui.button type="button" variant="ghost" wire:click="back" icon="fa-solid fa-arrow-left">{{ __('Back') }}</x-ui.button>
                        <x-ui.button variant="accent" wire:loading.attr="disabled" wire:target="create">
                            <span wire:loading.remove wire:target="create">{{ __('Create invitation') }}</span>
                            <span wire:loading wire:target="create">{{ __('Creating…') }}</span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            @if ($selectedTemplate)
                <div>
                    <p class="mb-2 text-sm font-medium text-ink">{{ __('Selected design') }}</p>
                    <x-template-card :template="$selectedTemplate" />
                </div>
            @endif
        </div>
    @endif
</div>
