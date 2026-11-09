{{-- The working column on the left; the format reference stays beside it, and below it on phones. --}}
<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="space-y-6">
        <x-ui.card :title="__('1. Choose the package')">
            <x-ui.upload-progress>
                <div class="relative flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed border-muted px-6 py-8 text-center transition hover:bg-paper focus-within:ring-2 focus-within:ring-sage focus-within:ring-offset-2">
                    <i class="fa-solid fa-file-zipper text-2xl text-sage" aria-hidden="true"></i>
                    <label for="html-package" class="mt-3 text-sm font-medium text-ink">
                        @if ($package && ! $errors->has('package'))
                            <span class="break-all">{{ $package->getClientOriginalName() }}</span>
                        @else
                            {{ __('Choose a .zip file or drop it here') }}
                        @endif
                    </label>
                    <p class="mt-1 text-xs text-muted">{{ $package && ! $errors->has('package') ? __('Choose or drop another file to replace it') : __('12 MB at most') }}</p>
                    <input id="html-package" type="file" accept=".zip,application/zip" wire:model="package" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </div>
            </x-ui.upload-progress>

            <p wire:loading wire:target="package" role="status" class="mt-3 text-sm text-ink-soft">
                <i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>{{ __('Checking the package...') }}
            </p>
            @error('package')
                <p class="mt-3 text-sm font-medium text-red-700"><i class="fa-solid fa-circle-xmark mr-1" aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </x-ui.card>

        @if ($report)
            <div wire:loading.class="hidden" wire:target="package" class="space-y-6">
                <x-ui.card :title="__('2. Check results')">
                    @if ($report['errors'] !== [])
                        <p class="text-sm font-medium text-red-700"><i class="fa-solid fa-circle-xmark mr-1" aria-hidden="true"></i>{{ trans_choice(':count problem must be fixed before importing.|:count problems must be fixed before importing.', count($report['errors'])) }}</p>
                        <ul class="mt-3 list-disc space-y-1.5 break-words pl-5 text-sm text-ink">
                            @foreach ($report['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-4 text-sm text-ink-soft">{{ __('Fix the file, then choose it again.') }}</p>
                    @else
                        <p class="text-sm font-medium text-sage"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ __('The package passed the check.') }}</p>
                        <dl class="mt-4 grid grid-cols-3 gap-4">
                            <div>
                                <dt class="text-xs text-muted">{{ __('Sections') }}</dt>
                                <dd class="mt-0.5 text-2xl tabular-nums text-ink">{{ count($report['summary']['sections']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">{{ __('Dynamic fields') }}</dt>
                                <dd class="mt-0.5 text-2xl tabular-nums text-ink">{{ $report['summary']['fields'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">{{ __('Files') }}</dt>
                                <dd class="mt-0.5 text-2xl tabular-nums text-ink">{{ $report['summary']['assets'] }}</dd>
                            </div>
                        </dl>
                        <p class="mt-4 text-sm text-ink-soft">{{ collect($report['summary']['sections'])->map(fn (string $type): string => __($sectionLabels[$type] ?? $type))->implode(', ') }}</p>
                    @endif

                    @if ($report['warnings'] !== [])
                        <div class="mt-5 border-t border-line pt-4">
                            <p class="text-sm font-medium text-ink"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ __('Worth checking') }}</p>
                            <ul class="mt-2 list-disc space-y-1.5 break-words pl-5 text-sm text-ink-soft">
                                @foreach ($report['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui.card>

                @if ($report['errors'] === [])
                    <x-ui.card :title="__('3. Save as a draft')" :description="__('The template stays hidden from clients until you publish it in the builder.')">
                        <form wire:submit="import" class="space-y-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.field name="name" for="html-name" :label="__('Template name')">
                                    <x-ui.input name="name" id="html-name" wire:model="name" maxlength="120" class="min-h-11" />
                                </x-ui.field>
                                <x-ui.field name="category" for="html-category" :label="__('Category')">
                                    <x-ui.select name="category" id="html-category" wire:model="category" class="min-h-11" :options="['' => __('Choose a category'), ...$categories]" />
                                </x-ui.field>
                            </div>
                            <x-ui.button variant="accent" size="lg" wire:loading.attr="disabled" wire:target="import">{{ __('Import as draft') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @endif
            </div>
        @endif
    </div>

    <x-ui.card :title="__('Package format')" class="lg:sticky lg:top-6">
        <pre class="overflow-x-auto rounded-lg bg-paper p-3 font-mono text-xs leading-relaxed text-ink">desain.zip
├── index.html
├── style.css
└── assets/</pre>
        <p class="mt-2 text-xs text-muted">{{ __('Both files sit at the top of the zip. Assets are JPG, PNG, WebP or WOFF fonts.') }}</p>

        <h3 class="mt-5 text-sm font-medium text-ink">{{ __('Marks in index.html') }}</h3>
        <dl class="mt-2 space-y-3 text-xs">
            @foreach ([
                '<section id="cover">' => __('One section. The id is the section name.'),
                'data-inv-field="title"' => __('Turns this text, image or link into a field.'),
                'data-inv-repeat="events"' => __('A repeating item, such as each event.'),
                'data-inv-effect="animate"' => __('Entrance animation. Use "next" to go to the next section.'),
            ] as $mark => $meaning)
                <div>
                    <dt class="break-all font-mono text-ink">{{ $mark }}</dt>
                    <dd class="mt-0.5 text-ink-soft">{{ $meaning }}</dd>
                </div>
            @endforeach
        </dl>

        <h3 class="mt-5 text-sm font-medium text-ink">{{ __('Section names') }}</h3>
        <p class="mt-1 break-words font-mono text-xs text-ink">{{ implode(', ', \App\Services\Template\HtmlDesign\HtmlDesignInspector::SECTIONS) }}</p>

        <p class="mt-5 border-t border-line pt-4 text-xs text-ink-soft">{{ __('Scripts and files from outside the package are refused. Use var(--inv-primary) and the other theme colors so the builder can recolor the design.') }}</p>
    </x-ui.card>
</div>
