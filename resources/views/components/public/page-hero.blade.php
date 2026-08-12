@props(['eyebrow' => null, 'title', 'lead' => null])

{{--
    The compact header every inner page opens with.

    Deliberately NOT the homepage's animated hero: that one is a
    full-viewport landing device, and repeating it on ten content pages
    would push the actual content below the fold everywhere. This keeps
    the same petrol/amber identity in a band tall enough to read and short
    enough to scroll past.
--}}
<header class="relative overflow-hidden bg-petrol-deep py-16 text-white sm:py-20">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute inset-0 bg-[linear-gradient(180deg,#081E2B_0%,#123C54_100%)]"></div>
        <div class="absolute -bottom-24 left-1/2 h-64 w-[70%] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse_at_center,rgba(232,106,34,0.4),transparent_65%)] blur-[26px]"></div>
    </div>

    <div class="wrap relative z-10 text-center">
        @if ($eyebrow)
            <span class="eyebrow eyebrow-dark">{{ $eyebrow }}</span>
        @endif

        <h1 class="text-3xl font-black leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ $title }}</h1>

        @if ($lead)
            <p class="mx-auto mt-4 max-w-2xl text-lg text-mist">{{ $lead }}</p>
        @endif

        {{ $slot ?? '' }}
    </div>
</header>
