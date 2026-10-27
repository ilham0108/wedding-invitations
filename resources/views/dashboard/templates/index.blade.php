<x-layouts.app area="dashboard" :title="__('Templates')">
    <x-slot:subtitle>
        <p class="mt-1 text-sm text-muted">{{ __('Pick a design to start a new invitation.') }}</p>
    </x-slot:subtitle>

    @if ($templates->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-swatchbook" :title="__('No templates are available yet.')" :description="__('Please check back soon.')" />
        </x-ui.card>
    @else
        <div x-data="{ category: 'all' }">
            <div class="mb-6 flex flex-wrap gap-2" role="tablist">
                <button type="button" x-on:click="category = 'all'" class="rounded-full border px-4 py-1.5 text-sm transition"
                    x-bind:class="category === 'all' ? 'border-ink bg-ink text-white' : 'border-line bg-white text-ink-soft hover:border-ink/30'">{{ __('All') }}</button>
                @foreach ($categories as $category)
                    <button type="button" x-on:click="category = @js($category->slug)" class="rounded-full border px-4 py-1.5 text-sm transition"
                        x-bind:class="category === @js($category->slug) ? 'border-ink bg-ink text-white' : 'border-line bg-white text-ink-soft hover:border-ink/30'">{{ $category->name }}</button>
                @endforeach
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <div x-show="category === 'all' || category === @js($template->category->slug)">
                        <x-template-card :template="$template" :use-url="route('dashboard.invitations.create', ['template' => $template->slug])" />
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.app>
