<x-layouts.app area="dashboard" :title="__('Templates')">
    <x-slot:subtitle>
        <p class="mt-1 text-sm text-muted">{{ __('Pick a design to start a new invitation.') }}</p>
    </x-slot:subtitle>

    @if ($templates->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-swatchbook" :title="__('No templates are available yet.')" :description="__('Please check back soon.')" />
        </x-ui.card>
    @else
        {{-- Category filter as links (?category=slug): the server filters and pages the list. --}}
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('Filter by category') }}">
            @foreach ([[null, __('All')], ...$categories->map(fn ($option): array => [$option->slug, $option->name])] as [$slug, $name])
                <a href="{{ route('dashboard.templates.index', array_filter(['category' => $slug])) }}" @if ($category === $slug) aria-current="page" @endif
                    @class(['inline-flex min-h-10 items-center rounded-full border px-4 text-sm transition', 'border-ink bg-ink text-white' => $category === $slug, 'border-line bg-white text-ink-soft hover:border-ink/30' => $category !== $slug])>{{ $name }}</a>
            @endforeach
        </nav>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @foreach ($templates as $template)
                <x-template-card :template="$template" :use-url="route('dashboard.invitations.create', ['template' => $template->slug])" />
            @endforeach
        </div>

        @if ($templates->hasPages())
            <div class="mt-6">{{ $templates->links() }}</div>
        @endif
    @endif
</x-layouts.app>
