@props([
    /*
     * The sign-in background.
     *
     * Defaults to the same generated dusk sky the public hero uses, so
     * the login screen is on-brand out of the box with no asset to ship.
     * Passing :video swaps in real footage with NO layout change — the
     * card, the overlay and the centring are all defined here and do not
     * move. That is the same contract x-public.hero offers, deliberately:
     * one background mechanism for the whole site.
     */
    'video' => null,
    'poster' => null,
])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0A2534">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? __('dashboard.auth.login_title') }} — {{ __('site.brand.name') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800,900&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        <div data-hero class="relative flex min-h-screen min-h-[100svh] w-full flex-col items-center justify-center overflow-hidden bg-petrol-deep px-4 py-10">
            <x-public.hero-backdrop :video="$video" :poster="$poster" />

            <div class="relative z-10 w-full sm:max-w-md">
                <div class="flex flex-col items-center text-center">
                    <a href="{{ route('home') }}" class="rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-2 focus-visible:ring-offset-petrol-deep">
                        <img src="{{ asset('logo-light.png') }}" alt="{{ __('site.brand.name') }}" width="530" height="363" class="h-24 w-auto">
                    </a>
                </div>

                {{--
                    bg-white/95 rather than something heavily transparent:
                    the backdrop behind is in motion (and may be video
                    later), and body text over a low-opacity surface stops
                    being readable as the footage changes. The blur lets
                    the movement register at the card's edges while the
                    form itself stays at full contrast.
                --}}
                <div class="mt-6 w-full rounded-3xl border border-white/30 bg-white/95 p-7 shadow-2xl shadow-petrol-deep/40 backdrop-blur-xl sm:p-9">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center">
                    <a href="{{ route('home') }}" class="rounded text-sm font-bold text-mist transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber">
                        {{ __('dashboard.auth.back_to_site') }}
                    </a>
                </p>
            </div>
        </div>
    </body>
</html>
