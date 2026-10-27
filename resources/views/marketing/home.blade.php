{{--
    Marketing home (botanical design). Copy and photos come from the admin CMS ($content,
    config/site-content.php); templates and plans from the database. Without uploaded photos,
    illustrated phones and line-art leaves stand in.
--}}
@php
    $hero = $content['hero'];
    $steps = $content['steps'];
    $featureItems = $content['features']['items'];
    $cta = $content['cta'];
    $em = fn (?string $text): string => \App\Support\Emphasis::html($text);
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
                <x-motif.sulur class="absolute bottom-0 left-10 w-56 text-olive/25" accent="#d9b8ad" />
                <div class="absolute inset-0 bg-gradient-to-r from-cream via-cream/10 to-transparent"></div>
            @endif
        </div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:min-h-[34rem] lg:grid-cols-2 lg:py-20">
            <div>
                @if (filled($hero['badge']))
                    <p class="mk-eyebrow mk-rise">{{ $hero['badge'] }}</p>
                @endif
                <h1 class="mk-serif mk-rise mk-rise-2 mt-4 text-[2.7rem] leading-[1.08] text-ink sm:text-[3.4rem]">{!! $em($hero['title']) !!}</h1>
                @if (filled($hero['subtitle']))
                    <p class="mk-rise mk-rise-3 mt-5 max-w-md text-[1.05rem] leading-relaxed text-ink-soft">{{ $hero['subtitle'] }}</p>
                @endif

                <div class="mk-rise mk-rise-3 mt-8 flex flex-wrap gap-3">
                    @if (filled($hero['secondary_label']))
                        <a href="#templates" class="inline-flex h-11 items-center gap-2 rounded-full bg-olive px-6 text-sm font-medium text-white transition hover:bg-olive-dark">
                            {{ $hero['secondary_label'] }} <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                        </a>
                    @endif
                    <a href="{{ route('register') }}" class="inline-flex h-11 items-center rounded-full border border-ink/25 bg-white/40 px-6 text-sm font-medium text-ink transition hover:border-ink/50">{{ $hero['primary_label'] }}</a>
                </div>

                @if ($hero['items'] !== [])
                    <ul class="mk-rise mk-rise-3 mt-12 grid max-w-md grid-cols-2 gap-6 sm:grid-cols-4">
                        @foreach ($hero['items'] as $item)
                            <li class="text-center">
                                <i class="{{ $item['icon'] }} text-xl text-ink-soft" aria-hidden="true"></i>
                                <p class="mt-2 text-[0.72rem] leading-snug text-ink-soft">{{ $item['title'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @unless ($hero['image'])
                {{-- Illustrated invitation on a phone. --}}
                <x-marketing.phone class="mk-rise mk-rise-2 mx-auto w-full max-w-[15.5rem] rotate-[4deg]">
                    <div class="absolute inset-0 flex flex-col items-center justify-center px-6 text-center">
                        <div class="absolute inset-x-7 bottom-16 top-14 rounded-t-full border border-olive/25"></div>
                        <p class="relative text-[8px] uppercase tracking-[0.3em] text-muted">{{ __('The Wedding of') }}</p>
                        <p class="mk-serif relative mt-3 text-[1.9rem] italic leading-[1.05] text-olive">Ana<br><span class="text-xl">&amp;</span><br>Budi</p>
                        <p class="relative mt-4 text-[9px] tracking-[0.25em] text-ink-soft">14 . 12 . 2026</p>
                        <span class="relative mt-6 rounded-full bg-olive px-4 py-1.5 text-[9px] font-medium text-white">{{ __('Open Invitation') }}</span>
                    </div>
                </x-marketing.phone>
            @else
                <img src="{{ $hero['image'] }}" alt="" class="w-full rounded-2xl object-cover lg:hidden">
            @endunless
        </div>
    </section>

    {{-- Featured templates --}}
    @if ($templates->isNotEmpty())
        <section id="templates" class="scroll-mt-20 bg-white/60 py-20" x-data="{ all: false }">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-xl">
                        @if (filled($content['templates']['eyebrow']))
                            <p class="mk-eyebrow">{{ $content['templates']['eyebrow'] }}</p>
                        @endif
                        <h2 class="mk-serif mt-3 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['templates']['heading']) !!}</h2>
                        @if (filled($content['templates']['subtitle']))
                            <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['templates']['subtitle'] }}</p>
                        @endif
                    </div>
                    @if ($templates->count() > 5 && filled($content['templates']['link_label']))
                        <button type="button" x-on:click="all = ! all" class="inline-flex items-center gap-2 text-sm text-ink-soft hover:text-ink">
                            <span x-text="all ? @js(__('Show fewer')) : @js($content['templates']['link_label'])">{{ $content['templates']['link_label'] }}</span>
                            <i class="fa-solid fa-arrow-right text-xs transition" x-bind:class="all && 'rotate-90'" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                <div class="mt-10 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($templates as $template)
                        <div @if ($loop->index >= 5) x-show="all" x-cloak x-transition.opacity @endif>
                            <x-template-card :template="$template" compact />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- How it works, with a phone that reaches down into the features. --}}
    @if ($steps['items'] !== [])
        <section id="how-it-works" class="relative scroll-mt-20 py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="max-w-3xl">
                    @if (filled($steps['eyebrow']))
                        <p class="mk-eyebrow">{{ $steps['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-3 text-4xl text-ink sm:text-[2.6rem]">{!! $em($steps['heading']) !!}</h2>
                    @if (filled($steps['subtitle']))
                        <p class="mt-3 leading-relaxed text-ink-soft">{{ $steps['subtitle'] }}</p>
                    @endif

                    <ol class="mt-10 grid gap-8 sm:grid-cols-2 lg:flex lg:items-start lg:gap-3">
                        @foreach ($steps['items'] as $index => $step)
                            <li class="flex flex-1 items-start gap-3 lg:contents">
                                <div class="text-center lg:flex-1">
                                    <span class="mk-icon-circle mx-auto h-16 w-16 text-xl"><i class="{{ $step['icon'] }}" aria-hidden="true"></i></span>
                                    <p class="mt-4 text-sm font-semibold text-ink">{{ $index + 1 }}. {{ $step['title'] }}</p>
                                    @if (filled($step['description'] ?? null))
                                        <p class="mx-auto mt-1.5 max-w-[11rem] text-[0.8rem] leading-relaxed text-muted">{{ $step['description'] }}</p>
                                    @endif
                                </div>
                                @unless ($loop->last)
                                    <i class="fa-solid fa-arrow-right-long mt-6 hidden text-lg text-olive-line lg:block" aria-hidden="true"></i>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="pointer-events-none absolute -bottom-40 right-[max(1rem,calc(50%-36rem))] z-10 hidden w-56 lg:block xl:w-60">
                @if ($steps['image'])
                    <img src="{{ $steps['image'] }}" alt="" class="mk-float w-full drop-shadow-2xl">
                @else
                    <x-marketing.phone class="mk-float">
                        <div class="absolute inset-0 flex flex-col">
                            <div class="h-[42%] bg-gradient-to-br from-[#c9d3c4] via-[#e3e6dc] to-[#cfd6cc]"></div>
                            <div class="flex flex-1 flex-col items-center px-5 pt-5 text-center">
                                <p class="mk-serif text-[1.2rem] leading-tight text-ink">{{ __('Your presence means a lot') }}</p>
                                <p class="mt-2 text-[8px] leading-relaxed text-muted">{{ __('We would be honoured if you could attend and give us your blessing.') }}</p>
                                <span class="mt-4 w-full rounded-full bg-olive py-1.5 text-[8px] font-medium text-white">{{ __('Confirm attendance') }}</span>
                                <span class="mt-2 w-full rounded-full border border-ink/20 py-1.5 text-[8px] text-ink">{{ __('See location') }}</span>
                            </div>
                        </div>
                    </x-marketing.phone>
                @endif
            </div>
        </section>
    @endif

    {{-- Features --}}
    @if ($featureItems !== [])
        <section id="features" class="scroll-mt-20 bg-cream-deep/70 py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="max-w-xl">
                    @if (filled($content['features']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['features']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-3 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['features']['heading']) !!}</h2>
                    @if (filled($content['features']['subtitle']))
                        <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['features']['subtitle'] }}</p>
                    @endif
                </div>

                <div class="mt-12 grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:mt-16 lg:grid-cols-3">
                    @foreach ($featureItems as $feature)
                        <div class="flex items-start gap-4">
                            <span class="mk-icon-circle h-14 w-14 text-lg"><i class="{{ $feature['icon'] }}" aria-hidden="true"></i></span>
                            <div>
                                <h3 class="font-semibold text-ink">{{ $feature['title'] }}</h3>
                                @if (filled($feature['description'] ?? null))
                                    <p class="mt-1 text-sm leading-relaxed text-muted">{{ $feature['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Pricing (plans and prices from the database) --}}
    @if ($plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-20 py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-xl text-center">
                    @if (filled($content['pricing']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['pricing']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-3 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['pricing']['heading']) !!}</h2>
                    @if (filled($content['pricing']['subtitle']))
                        <p class="mt-3 leading-relaxed text-ink-soft">{{ $content['pricing']['subtitle'] }}</p>
                    @endif
                </div>

                <div class="mt-12 grid items-stretch gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($plans as $plan)
                        @php($dark = $plan->is_highlighted)
                        <article @class([
                            'relative flex flex-col rounded-2xl p-7',
                            'bg-olive text-white shadow-[0_30px_60px_-30px_rgb(58_69_50/0.8)]' => $dark,
                            'border border-olive-line bg-white/70' => ! $dark,
                        ])>
                            @if ($dark)
                                <span class="absolute -top-3 left-7 rounded-full bg-cream px-3 py-1 text-[0.7rem] font-semibold text-olive">{{ __('Popular') }}</span>
                            @endif
                            <h3 @class(['font-semibold', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ $plan->name }}</h3>
                            @if ($plan->description)
                                <p @class(['mt-1 min-h-[2.5rem] text-sm', 'text-white/70' => $dark, 'text-muted' => ! $dark])>{{ $plan->description }}</p>
                            @endif
                            <div class="mt-5">
                                @if ($plan->is_free || $plan->activePrices->isEmpty())
                                    <p @class(['mk-serif text-4xl', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ __('Free') }}</p>
                                @else
                                    @foreach ($plan->activePrices as $price)
                                        <p @class(['mk-serif text-4xl' => $loop->first, 'mt-1 text-sm' => ! $loop->first, 'text-white' => $dark, 'text-ink' => ! $dark])>
                                            {{ \App\Support\Money::format($price->amount, $price->currency) }}
                                            <span @class(['font-sans text-sm', 'text-white/70' => $dark, 'text-muted' => ! $dark])>{{ $price->periodLabel() }}</span>
                                        </p>
                                    @endforeach
                                @endif
                            </div>
                            <ul @class(['mt-6 flex-1 space-y-2.5 border-t pt-6 text-sm', 'border-white/15' => $dark, 'border-olive-line' => ! $dark])>
                                @foreach ($plan->resolvedFeatures() as $feature => $value)
                                    @continue($value === false)
                                    <li @class(['flex items-start gap-2.5', 'text-white/85' => $dark, 'text-ink-soft' => ! $dark])>
                                        <i @class(['fa-solid fa-check mt-1 text-xs', 'text-white' => $dark, 'text-olive' => ! $dark]) aria-hidden="true"></i>
                                        <span>
                                            @if ($features[$feature]['type'] === 'limit')
                                                <span @class(['font-semibold', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ $value === null ? __('Unlimited') : Number::format($value) }}</span>
                                            @endif
                                            {{ __($features[$feature]['label']) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" @class([
                                'mt-8 inline-flex h-11 items-center justify-center rounded-full text-sm font-medium transition',
                                'bg-cream text-olive hover:bg-white' => $dark,
                                'border border-ink/20 text-ink hover:border-ink/40' => ! $dark,
                            ])>{{ $plan->is_free ? __('Get started for free') : __('Choose :plan', ['plan' => $plan->name]) }}</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ (hidden until the admin adds a question) --}}
    @if ($content['faq']['items'] !== [])
        <section id="faq" class="scroll-mt-20 bg-white/60 py-20">
            <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    @if (filled($content['faq']['eyebrow']))
                        <p class="mk-eyebrow">{{ $content['faq']['eyebrow'] }}</p>
                    @endif
                    <h2 class="mk-serif mt-3 text-4xl text-ink sm:text-[2.6rem]">{!! $em($content['faq']['heading']) !!}</h2>
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
                            <p class="mt-3 whitespace-pre-line pr-8 leading-relaxed text-muted">{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Call to action over a wide photo (or a soft illustrated background). --}}
    <section class="relative overflow-hidden">
        @if ($cta['image'])
            <img src="{{ $cta['image'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgb(247_244_238/0.92),rgb(247_244_238/0.55)_70%)]"></div>
        @else
            <div class="mk-hero-fallback absolute inset-0"></div>
            <x-motif.sulur class="absolute -left-4 bottom-0 hidden w-64 text-olive/30 sm:block" accent="#d9b8ad" />
            <x-motif.sulur class="absolute -right-4 top-0 hidden w-64 -scale-100 text-olive/30 sm:block" accent="#d9b8ad" />
        @endif

        <div class="relative mx-auto max-w-2xl px-4 py-24 text-center sm:px-6">
            @if (filled($cta['eyebrow']))
                <p class="mk-eyebrow">{{ $cta['eyebrow'] }}</p>
            @endif
            <h2 class="mk-serif mt-4 text-4xl leading-tight text-ink sm:text-[2.8rem]">{!! $em($cta['heading']) !!}</h2>
            @if (filled($cta['text']))
                <p class="mx-auto mt-4 max-w-md leading-relaxed text-ink-soft">{{ $cta['text'] }}</p>
            @endif
            <a href="{{ route('register') }}" class="mt-8 inline-flex h-11 items-center gap-2 rounded-full bg-olive px-7 text-sm font-medium text-white transition hover:bg-olive-dark">
                {{ $cta['button_label'] }} <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
            </a>
        </div>
    </section>
</x-layouts.marketing>
