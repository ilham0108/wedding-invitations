{{-- Illustration picker modal for components using EditsSectionFields. --}}
@if ($pickerCategory)
    @teleport('body')
    <div class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="illustration-picker-title"
        x-data x-on:keydown.escape.window="$wire.closePicker()">
        <div class="fixed inset-0 bg-ink/40" wire:click="closePicker"></div>
        <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <div>
                    <h2 id="illustration-picker-title" class="font-semibold text-ink">{{ __('Choose illustration') }}</h2>
                    <p class="text-xs text-muted">{{ $pickerCategory->label() }}</p>
                </div>
                <button type="button" wire:click="closePicker" class="rounded p-1.5 text-muted hover:bg-paper hover:text-ink" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="overflow-y-auto p-5">
                @error('picker')
                    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                        {{ $message }} <a href="{{ route('dashboard.subscription.index') }}" class="font-medium underline">{{ __('See plans') }}</a>
                    </div>
                @enderror
                @if ($pickerIllustrations->isEmpty())
                    <p class="py-8 text-center text-sm text-muted">{{ __('No illustrations in this category yet.') }}</p>
                @else
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($pickerIllustrations as $illustration)
                            <li wire:key="illustration-{{ $illustration->ulid }}">
                                <button type="button" wire:click="pickIllustration('{{ $illustration->ulid }}')" wire:loading.attr="disabled"
                                    class="group relative block w-full overflow-hidden rounded-lg border border-line bg-paper text-left hover:border-sage focus:outline-none focus-visible:ring-2 focus-visible:ring-sage">
                                    <img src="{{ $illustration->url() }}" alt="" loading="lazy" decoding="async" class="aspect-square w-full object-contain p-2 transition group-hover:scale-[1.03]">
                                    <span class="block truncate border-t border-line bg-white px-2 py-1.5 text-xs text-ink-soft">{{ $illustration->name }}</span>
                                    @if ($illustration->is_premium)
                                        <span class="absolute right-1.5 top-1.5"><x-ui.badge variant="warning">{{ __('Premium') }}</x-ui.badge></span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @endteleport
@endif
