@props([
    'video' => null,
    'poster' => null,
])

@php
    /*
     * The three hero figures.
     *
     * Every one comes from settings and is seeded EMPTY, so a fresh
     * install shows no stats block at all rather than the design
     * reference's "+500 companies / 48h / 98%" — numbers nobody has
     * verified have no business on the most visible part of the site.
     *
     * The VALUE is the business fact and lives in settings (editable, and
     * per-locale because "٤٨ ساعة" and "48 hours" are different strings).
     * The LABEL beside it is UI chrome and lives in the lang files.
     */
    $settings = app(App\Services\Cms\SettingsRepository::class);

    $stats = collect([
        'companies' => $settings->translated('home_stat_companies'),
        'turnaround' => $settings->translated('home_stat_turnaround'),
        'satisfaction' => $settings->translated('home_stat_satisfaction'),
    ])->filter();
@endphp

{{--
    The hero shell.

    Deliberately thin: it owns the BOX (height, stacking, content
    alignment) and nothing about what is drawn behind the text. The
    backdrop is a single child component, so replacing the generated sky
    with a <video> is a matter of passing :video — the height, the overlay
    and the content stack are all defined here and do not move.

    min-h uses svh with a vh fallback: on mobile Safari, 90vh is measured
    against the browser chrome's *collapsed* height, so a 90vh hero is
    taller than the visible viewport on first paint and the CTA sits below
    the fold. svh measures the smallest stable viewport, which is what
    "fills the screen" actually means on a phone.
--}}
<header
    data-hero
    class="relative flex min-h-[88vh] min-h-[88svh] items-center overflow-hidden bg-petrol-deep text-white"
>
    <x-public.hero-backdrop :video="$video" :poster="$poster" />

    <div class="wrap relative z-10 py-20 text-center">
        <p class="sr-only">{{ __('site.hero.background_label') }}</p>

        <span class="eyebrow eyebrow-dark">{{ __('site.hero.eyebrow') }}</span>

        {{-- The highlight is `amber` (5.69:1 on petrol), not the brand
             orange (3.62:1) — a display word still has to be readable.
             The underline bar behind it is decorative and carries no
             contrast requirement of its own. --}}
        <h1 class="text-4xl font-black leading-[1.1] tracking-tight [text-shadow:0_2px_30px_rgba(0,0,0,0.35)] sm:text-5xl lg:text-[4.5rem]">
            {{ __('site.hero.title_before') }}
            <span class="relative inline-block whitespace-nowrap text-amber">
                <span class="relative z-10">{{ __('site.hero.title_highlight') }}</span>
                <span class="absolute inset-x-0 bottom-1 z-0 h-3 rounded bg-orange/55" aria-hidden="true"></span>
            </span>
            {{ __('site.hero.title_after') }}
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-lg text-[#E0E9EF] [text-shadow:0_1px_12px_rgba(0,0,0,0.3)] sm:text-xl">
            {{ __('site.hero.lead') }}
        </p>

        <div class="mt-9 flex flex-wrap items-center justify-center gap-3.5">
            {{-- Real destinations, not homepage fragments. "ابدأ الآن"
                 opens the booking page and "احسب تكلفتك" the estimator —
                 the two entry points of the funnel. --}}
            <a href="{{ lroute('booking') }}" class="btn btn-primary btn-on-dark">{{ __('site.hero.cta_primary') }}</a>
            <a href="{{ lroute('estimator') }}" class="btn btn-ghost-light btn-on-dark">{{ __('site.hero.cta_secondary') }}</a>
        </div>

        @if ($stats->isNotEmpty())
            {{-- Individually conditional as well as collectively: an admin
                 who has filled in one figure gets one, not two blanks
                 beside it. --}}
            <dl class="mt-12 flex flex-wrap items-start justify-center gap-x-12 gap-y-6">
                @foreach ($stats as $key => $value)
                    <div class="text-center">
                        <dt class="sr-only">{{ __('site.hero.stats.'.$key) }}</dt>
                        <dd>
                            <span class="block text-3xl font-black text-amber sm:text-4xl">{{ $value }}</span>
                            <span class="mt-1 block text-sm text-mist">{{ __('site.hero.stats.'.$key) }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{ $slot }}
    </div>

    {{-- Scroll cue. A link rather than a bare glyph so it is reachable and
         actually does something when activated; the arrow itself is
         aria-hidden and the accessible name comes from the label. --}}
    <a
        href="#services"
        class="absolute bottom-6 start-1/2 z-10 -translate-x-1/2 rounded-full p-2 text-white/70 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white rtl:translate-x-1/2"
    >
        <span class="sr-only">{{ __('site.hero.scroll_cue') }}</span>
        <svg class="h-6 w-6 animate-scroll-cue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke-linecap="round" />
        </svg>
    </a>
</header>
