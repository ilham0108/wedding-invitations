{{--
    Marketing home (botanical design). Copy and photos come from the admin CMS ($content,
    config/site-content.php); templates and plans from the database. The page leads with the
    product itself: a real published template running in a phone frame.
--}}
@php
    $hero = $content['hero'];
    $demo = $content['demo'];
    $steps = $content['steps'];
    $featureItems = $content['features']['items'];
    $cta = $content['cta'];
    $em = fn (?string $text): string => \App\Support\Emphasis::html($text);

    $demoTemplate = $templates->firstWhere('slug', $demo['template']) ?? $templates->first();
    $demoOptions = $templates->map(fn ($template): array => [
        'slug' => $template->slug,
        'name' => $template->name,
        'embed' => route('templates.preview', ['template' => $template, 'embed' => 1]),
        'full' => route('templates.preview', $template),
    ])->values();
    $sectionLabels = collect(config('invitation.sections'))->map(fn (array $section): string => __($section['label']));

    // Rows of the pricing table: features at least one plan includes, in catalogue order.
    $planFeatures = $plans->mapWithKeys(fn ($plan): array => [$plan->id => $plan->resolvedFeatures()]);
    $featureRows = collect($features)->keys()->filter(
        fn (string $key): bool => $planFeatures->contains(fn (array $values): bool => ($values[$key] ?? false) !== false)
    );
    $categoryOf = fn ($template): string => (string) $template->category->slug;
@endphp

<x-layouts.marketing :title="\App\Support\Emphasis::plain($hero['title'])" :description="$hero['subtitle']">
    {{-- Hero: copy on the left, photo (or illustrated phone) on the right. --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-y-0 right-0 hidden w-[52%] lg:block" aria-hidden="true">
            @if ($hero['image'])
                <img src="{{ $hero['image'] }}" alt="" class="h-full w-full object-cover" fetchpriority="high">
                <div class="absolute inset-0 bg-gradient-to-r from-cream via-cream/30 to-transparent"></div>
            @else
                <div class="mk-hero-fallback absolute inset-0"></div>
                <x-motif.sulur class="absolute -right-6 top-10 w-72 -scale-x-100 text-olive/30" accent="#d9b8ad" />
                <div class="absolute inset-0 bg-gradient-to-r from-cream via-cream/10 to-transparent"></div>
            @endif
        </div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 pb-14 pt-12 sm:px-6 lg:min-h-[36rem] lg:grid-cols-2 lg:py-20">
            <div>
                @if (filled($hero['badge']))
                    <p class="mk-eyebrow mk-rise">{{ $hero['badge'] }}</p>
                @endif
                <h1 class="mk-serif mk-rise mk-rise-2 mt-3 text-[2.6rem] leading-[1.06] text-ink sm:text-[3.6rem]">{!! $em($hero['title']) !!}</h1>
                @if (filled($hero['subtitle']))
                    <p class="mk-rise mk-rise-3 mt-5 max-w-md text-[1.05rem] leading-relaxed text-ink-soft">{{ $hero['subtitle'] }}</p>
                @endif

                {{-- Signing up is the main action; browsing templates is the quieter second one. --}}
                <div class="mk-rise mk-rise-3 mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <a href="{{ route('register') }}" class="inline-flex h-12 items-center justify-center rounded-full bg-olive px-7 text-sm font-medium text-white transition hover:bg-olive-dark active:bg-olive-dark">{{ $hero['primary_label'] }}</a>
                    @if (filled($hero['secondary_label']) && $templates->isNotEmpty())
                        <a href="#templates" class="inline-flex h-12 items-center justify-center rounded-full border border-ink/25 bg-white/40 px-7 text-sm font-medium text-ink transition hover:border-ink/50 hover:bg-white/70">{{ $hero['secondary_label'] }}</a>
                    @endif
                </div>

                @if ($hero['items'] !== [])
                    <ul class="mk-rise mk-rise-3 mt-10 flex max-w-lg flex-wrap gap-x-6 gap-y-2 text-sm text-ink-soft">
                        @foreach ($hero['items'] as $item)
                            <li class="flex items-center gap-2">
                                <i class="{{ $item['icon'] }} text-olive" aria-hidden="true"></i>
                                <span>{{ $item['title'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($hero['image'])
                <img src="{{ $hero['image'] }}" alt="" class="aspect-[4/3] w-full rounded-2xl object-cover lg:hidden" fetchpriority="high">
            @endif
        </div>
    </section>

    {{-- Live demo: the focal point. A real template scrolls to the part each item names. --}}
    @if ($demoTemplate)
        <section id="demo" class="scroll-mt-16 bg-olive-dark py-16 text-white sm:py-24"
            x-data="mkDemo(@js($demoOptions), @js($demoTemplate->slug))">
            <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_auto] lg:gap-20">
                <div class="lg:order-1">
                    @if (filled($demo['eyebrow']))
                        <p class="mk-eyebrow mk-eyebrow--light">{{ $demo['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mk-em-light mt-2 text-4xl leading-tight sm:text-[2.9rem]">{!! $em($demo['heading']) !!}</h2>
                    @if (filled($demo['subtitle']))
                        <p class="mt-4 max-w-lg leading-relaxed text-white/85">{{ $demo['subtitle'] }}</p>
                    @endif

                    @if ($demo['items'] !== [])
                        {{-- Phones get compact chips so the phone frame stays in view right below them. --}}
                        <ul class="mt-8 grid grid-cols-2 gap-2 lg:grid-cols-1 lg:gap-1">
                            @foreach ($demo['items'] as $item)
                                <li>
                                    <button type="button" x-on:click="go(@js($item['section']))"
                                        x-bind:disabled="! has(@js($item['section']))"
                                        x-bind:aria-pressed="(active === @js($item['section'])).toString()"
                                        x-bind:class="active === @js($item['section']) ? 'bg-cream text-ink' : 'text-white hover:bg-white/10'"
                                        class="flex min-h-11 w-full items-start gap-3 rounded-xl border border-white/15 px-3 py-2.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50 lg:gap-4 lg:border-transparent lg:px-4 lg:py-3 lg:text-base">
                                        <i class="{{ $item['icon'] }} mt-1 w-5 shrink-0 text-center" aria-hidden="true"></i>
                                        <span>
                                            <span class="block font-medium">{{ $item['title'] }}</span>
                                            @if (filled($item['description'] ?? null))
                                                <span class="mt-0.5 hidden text-sm leading-relaxed opacity-85 lg:block">{{ $item['description'] }}</span>
                                            @endif
                                            <span x-show="loaded && ! has(@js($item['section']))" x-cloak class="mt-0.5 block text-xs">{{ __('Not in this design (:section)', ['section' => $sectionLabels[$item['section']] ?? $item['section']]) }}</span>
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="mx-auto w-full max-w-[18.5rem] lg:order-2 lg:w-[18.5rem]">
                    {{-- Phone frame; the iframe is the bare template preview (?embed=1). --}}
                    <div class="rounded-[2.6rem] border-[10px] border-[#20241d] bg-[#20241d] shadow-[0_40px_80px_-40px_rgb(0_0_0/0.7)]">
                        {{-- The page renders at a real phone's width and is scaled down, so the demo looks as it does on a phone. --}}
                        <div class="relative h-[34rem] overflow-hidden rounded-[2rem] bg-cream sm:h-[35rem]"
                            x-init="new ResizeObserver(([entry]) => { const scale = entry.contentRect.width / 390; $refs.frame.style.transform = `scale(${scale})`; $refs.frame.style.height = `${entry.contentRect.height / scale}px`; }).observe($el)">
                            <iframe x-ref="frame" src="{{ route('templates.preview', ['template' => $demoTemplate, 'embed' => 1]) }}"
                                x-on:load="ready()" title="{{ __('Live invitation demo') }}" loading="lazy"
                                width="390" height="770" class="block max-w-none origin-top-left"></iframe>
                            <p x-show="! loaded" class="absolute inset-0 flex items-center justify-center text-sm text-ink-soft">{{ __('Loading the invitation...') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex items-end gap-3">
                        <div class="min-w-0 flex-1">
                            <label for="mk-demo-template" class="block text-xs text-white/80">{{ __('Design') }}</label>
                            <select id="mk-demo-template" x-model="slug" x-on:change="switchTo()" class="mt-1 block w-full rounded-full border-white/25 bg-white/10 py-2.5 pl-4 pr-9 text-sm text-white focus:border-cream focus:ring-cream">
                                @foreach ($demoOptions as $option)
                                    <option value="{{ $option['slug'] }}" class="text-ink">{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <a x-bind:href="current().full" href="{{ route('templates.preview', $demoTemplate) }}" target="_blank" rel="noopener"
                            class="inline-flex h-11 shrink-0 items-center gap-2 rounded-full px-3 text-sm text-white/90 underline-offset-4 hover:text-white hover:underline">
                            {{ __('Full screen') }} <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                            <span class="sr-only">({{ __('opens in a new tab') }})</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Template catalogue, filtered by category. --}}
    @if ($templates->isNotEmpty())
        <section id="templates" class="scroll-mt-16 py-16 sm:py-20" x-data="{ category: 'all', all: false }">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div class="max-w-xl">
                        @if (filled($content['templates']['eyebrow']))
                            <p class="mk-eyebrow">{{ $content['templates']['eyebrow'] }}</p>
                        @endif
                        <h2 class="mk-serif mt-2 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['templates']['heading']) !!}</h2>
                        @if (filled($content['templates']['subtitle']))
                            <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['templates']['subtitle'] }}</p>
                        @endif
                    </div>

                    @if ($categories->count() > 1)
                        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter by category') }}">
                            @foreach ([['all', __('All')], ...$categories->map(fn ($category): array => [$category->slug, $category->name])] as [$slug, $name])
                                <button type="button" x-on:click="category = @js($slug)" x-bind:aria-pressed="(category === @js($slug)).toString()"
                                    x-bind:class="category === @js($slug) ? 'border-olive bg-olive text-white' : 'border-olive-line text-ink-soft hover:border-ink/40 hover:text-ink'"
                                    class="h-11 rounded-full border px-4 text-sm transition">{{ $name }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-10 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($templates as $template)
                        <div x-show="(category === 'all' || category === @js($categoryOf($template))) && (all || category !== 'all' || {{ $loop->index }} < 10)" x-transition.opacity>
                            <x-template-card :template="$template" compact />
                        </div>
                    @endforeach
                </div>

                @if ($templates->count() > 10 && filled($content['templates']['link_label']))
                    <div class="mt-10 text-center" x-show="category === 'all'">
                        <button type="button" x-on:click="all = ! all" class="h-11 rounded-full border border-olive-line px-6 text-sm text-ink transition hover:border-ink/40">
                            <span x-text="all ? @js(__('Show fewer')) : @js($content['templates']['link_label'])">{{ $content['templates']['link_label'] }}</span>
                        </button>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- How it works: serif numerals read like the numbered pages of a printed invitation. --}}
    @if ($steps['items'] !== [])
        <section id="how-it-works" class="scroll-mt-16 border-t border-olive-line/70 py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="grid gap-4 lg:grid-cols-[1fr_1fr] lg:items-end">
                    <div>
                        @if (filled($steps['eyebrow']))
                            <p class="mk-eyebrow">{{ $steps['eyebrow'] }}</p>
                        @endif
                        <h2 class="mk-serif mt-2 text-4xl text-ink sm:text-[2.6rem]">{!! $em($steps['heading']) !!}</h2>
                    </div>
                    @if (filled($steps['subtitle']))
                        <p class="max-w-md leading-relaxed text-ink-soft lg:justify-self-end">{{ $steps['subtitle'] }}</p>
                    @endif
                </div>

                <ol @class([
                    'mt-10 grid gap-x-8 gap-y-8 sm:grid-cols-2',
                    'lg:grid-cols-4' => count($steps['items']) === 4,
                    'lg:grid-cols-3' => count($steps['items']) !== 4,
                ])>
                    @foreach ($steps['items'] as $index => $step)
                        <li class="border-t border-olive-line pt-5">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="mk-serif text-[2.6rem] leading-none text-olive">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <i class="{{ $step['icon'] }} text-ink-soft" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-4 font-semibold text-ink">{{ $step['title'] }}</h3>
                            @if (filled($step['description'] ?? null))
                                <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">{{ $step['description'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Features: heading held on the left, the list runs down the right like an index. --}}
    @if ($featureItems !== [])
        <section id="features" class="scroll-mt-16 bg-cream-deep/70 py-16 sm:py-20">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.75fr_1.6fr] lg:gap-16">
                <div class="lg:sticky lg:top-28 lg:self-start">
                    @if (filled($content['features']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['features']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-2 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['features']['heading']) !!}</h2>
                    @if (filled($content['features']['subtitle']))
                        <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['features']['subtitle'] }}</p>
                    @endif
                </div>

                <ul class="grid gap-x-10 sm:grid-cols-2">
                    @foreach ($featureItems as $feature)
                        <li class="flex items-start gap-4 border-t border-olive-line py-5">
                            <i class="{{ $feature['icon'] }} mt-1 w-5 shrink-0 text-center text-olive" aria-hidden="true"></i>
                            <div>
                                <h3 class="font-semibold text-ink">{{ $feature['title'] }}</h3>
                                @if (filled($feature['description'] ?? null))
                                    <p class="mt-1 text-sm leading-relaxed text-ink-soft">{{ $feature['description'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Pricing (plans and prices from the database): a comparison table on wide screens, cards below. --}}
    @if ($plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-16 py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="max-w-xl">
                    @if (filled($content['pricing']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['pricing']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-2 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['pricing']['heading']) !!}</h2>
                    @if (filled($content['pricing']['subtitle']))
                        <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['pricing']['subtitle'] }}</p>
                    @endif
                </div>

                <table class="mt-12 hidden w-full table-fixed border-collapse text-sm lg:table">
                    <caption class="sr-only">{{ __('Plan comparison') }}</caption>
                    <colgroup>
                        <col class="w-[22%]">
                        @foreach ($plans as $plan)
                            <col @class(['bg-olive-soft/60' => $plan->is_highlighted])>
                        @endforeach
                    </colgroup>
                    {{-- Name, price and button sit in their own rows so they line up across plans. --}}
                    <thead>
                        <tr class="align-top">
                            <td></td>
                            @foreach ($plans as $plan)
                                <th scope="col" class="px-5 pt-6 text-left font-normal">
                                    <span class="flex items-center gap-2">
                                        <span class="text-base font-semibold text-ink">{{ $plan->name }}</span>
                                        @if ($plan->is_highlighted)
                                            <span class="rounded-full bg-olive px-2 py-0.5 text-[0.7rem] font-medium text-white">{{ __('Popular') }}</span>
                                        @endif
                                    </span>
                                    @if ($plan->description)
                                        <span class="mt-1 block text-ink-soft">{{ $plan->description }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        <tr class="align-top">
                            <td></td>
                            @foreach ($plans as $plan)
                                <td class="px-5">@include('marketing.partials.plan-price', ['plan' => $plan, 'compact' => true])</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td></td>
                            @foreach ($plans as $plan)
                                <td class="px-5 pb-6 pt-2">
                                    <a href="{{ route('register') }}" @class([
                                        'inline-flex h-11 w-full items-center justify-center rounded-full text-sm font-medium transition',
                                        'bg-olive text-white hover:bg-olive-dark' => $plan->is_highlighted,
                                        'border border-ink/20 text-ink hover:border-ink/40 hover:bg-white' => ! $plan->is_highlighted,
                                    ])>{{ $plan->is_free ? __('Get started for free') : __('Choose :plan', ['plan' => $plan->name]) }}</a>
                                </td>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($featureRows as $feature)
                            <tr class="border-t border-olive-line">
                                <th scope="row" class="py-3.5 pr-4 text-left font-normal text-ink-soft">
                                    {{ __($features[$feature]['label']) }}
                                    @if ($features[$feature]['coming_soon'] ?? false)
                                        <span class="ml-1 whitespace-nowrap rounded-full border border-olive-line px-2 py-0.5 text-[0.7rem] text-ink-soft">{{ __('Coming soon') }}</span>
                                    @endif
                                </th>
                                @foreach ($plans as $plan)
                                    @php($value = $planFeatures[$plan->id][$feature] ?? false)
                                    <td class="px-5 py-3.5 text-ink">
                                        @if ($value === false)
                                            <i class="fa-solid fa-minus text-xs text-ink-soft/60" aria-hidden="true"></i><span class="sr-only">{{ __('Not included') }}</span>
                                        @elseif ($features[$feature]['type'] === 'limit')
                                            <span class="font-medium">{{ $value === null ? __('Unlimited') : Number::format($value) }}</span>
                                        @else
                                            <i class="fa-solid fa-check text-olive" aria-hidden="true"></i><span class="sr-only">{{ __('Included') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:hidden">
                    @foreach ($plans as $plan)
                        <article @class([
                            'flex flex-col rounded-2xl border p-6',
                            'border-olive bg-olive-soft/60' => $plan->is_highlighted,
                            'border-olive-line bg-white/60' => ! $plan->is_highlighted,
                        ])>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-semibold text-ink">{{ $plan->name }}</h3>
                                @if ($plan->is_highlighted)
                                    <span class="rounded-full bg-olive px-2 py-0.5 text-[0.7rem] font-medium text-white">{{ __('Popular') }}</span>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="mt-1 text-sm text-ink-soft">{{ $plan->description }}</p>
                            @endif
                            @include('marketing.partials.plan-price', ['plan' => $plan])
                            <ul class="mt-5 flex-1 space-y-2 border-t border-olive-line pt-5 text-sm text-ink-soft">
                                @foreach ($planFeatures[$plan->id] as $feature => $value)
                                    @continue($value === false || ! isset($features[$feature]))
                                    <li class="flex items-start gap-2.5">
                                        <i class="fa-solid fa-check mt-1 text-xs text-olive" aria-hidden="true"></i>
                                        <span>
                                            @if ($features[$feature]['type'] === 'limit')
                                                <span class="font-semibold text-ink">{{ $value === null ? __('Unlimited') : Number::format($value) }}</span>
                                            @endif
                                            {{ __($features[$feature]['label']) }}
                                            @if ($features[$feature]['coming_soon'] ?? false)
                                                <span class="ml-1 whitespace-nowrap rounded-full border border-olive-line px-2 py-0.5 text-[0.7rem]">{{ __('Coming soon') }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" @class([
                                'mt-6 inline-flex h-11 items-center justify-center rounded-full text-sm font-medium transition',
                                'bg-olive text-white hover:bg-olive-dark' => $plan->is_highlighted,
                                'border border-ink/20 text-ink hover:border-ink/40 hover:bg-white' => ! $plan->is_highlighted,
                            ])>{{ $plan->is_free ? __('Get started for free') : __('Choose :plan', ['plan' => $plan->name]) }}</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ (hidden until the admin adds a question) --}}
    @if ($content['faq']['items'] !== [])
        <section id="faq" class="scroll-mt-16 border-t border-olive-line/70 py-16 sm:py-20">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-12">
                <div>
                    @if (filled($content['faq']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['faq']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-2 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['faq']['heading']) !!}</h2>
                    @if (filled($content['contact']['email']))
                        <p class="mt-4 text-ink-soft">{{ __('Still have a question?') }} <a href="mailto:{{ $content['contact']['email'] }}" class="font-medium text-olive underline-offset-4 hover:underline">{{ $content['contact']['email'] }}</a></p>
                    @endif
                </div>
                <div class="divide-y divide-olive-line border-y border-olive-line">
                    @foreach ($content['faq']['items'] as $item)
                        <details class="group py-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-ink">
                                {{ $item['question'] }}
                                <i class="fa-solid fa-plus text-xs text-olive transition group-open:rotate-45" aria-hidden="true"></i>
                            </summary>
                            <p class="mt-3 whitespace-pre-line pr-8 leading-relaxed text-ink-soft">{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Closing, set like the card inside an invitation envelope: a double hairline frame. --}}
    <section class="relative overflow-hidden px-4 py-16 sm:px-6 sm:py-24">
        @if ($cta['image'])
            <img src="{{ $cta['image'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-cream/70"></div>
        @else
            <div class="mk-hero-fallback absolute inset-0"></div>
        @endif

        <div class="relative mx-auto max-w-2xl border border-olive-line bg-cream/90 p-2">
            <div class="border border-olive-line px-6 py-12 text-center sm:px-12 sm:py-16">
                @if (filled($cta['eyebrow']))
                    <p class="mk-eyebrow">{{ $cta['eyebrow'] }}</p>
                @endif
                <h2 class="mk-serif mt-3 text-4xl leading-tight text-ink sm:text-[2.7rem]">{!! $em($cta['heading']) !!}</h2>
                @if (filled($cta['text']))
                    <p class="mx-auto mt-4 max-w-md leading-relaxed text-ink-soft">{{ $cta['text'] }}</p>
                @endif
                <a href="{{ route('register') }}" class="mt-8 inline-flex h-12 items-center rounded-full bg-olive px-8 text-sm font-medium text-white transition hover:bg-olive-dark active:bg-olive-dark">{{ $cta['button_label'] }}</a>
            </div>
        </div>
    </section>
</x-layouts.marketing>
