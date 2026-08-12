{{--
    Shared shell for every error page.

    Uses the SAME hero backdrop as the homepage and the login screen, so a
    404 still looks like this site rather than a framework default — which
    is the whole point of branding error pages: they are the screens a
    visitor is most likely to hit when something is already going wrong.

    NO layout composer runs here. An exception response is rendered by the
    handler, which means the view composer that supplies $menu/$siteName to
    the public layout has not run — so these pages deliberately depend on
    nothing but translations and routes. Extending layouts.public instead
    would 500 the error page itself, which is the one page that must never
    fail.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="theme-color" content="#0A2534">

        <title>@yield('title') — {{ __('site.brand.name') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800,900&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        <main data-hero class="relative flex min-h-screen min-h-[100svh] flex-col items-center justify-center overflow-hidden bg-petrol-deep px-6 py-16 text-center text-white">
            <x-public.hero-backdrop />

            <div class="relative z-10 w-full max-w-lg">
                <a href="{{ url('/') }}" class="inline-block rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber">
                    <img src="{{ asset('logo-light.png') }}" alt="{{ __('site.brand.name') }}" width="530" height="363" class="mx-auto h-20 w-auto">
                </a>

                <p class="mt-8 text-6xl font-black text-amber sm:text-7xl" dir="ltr">@yield('code')</p>

                <h1 class="mt-3 text-2xl font-black sm:text-3xl">@yield('title')</h1>

                <p class="mx-auto mt-3 max-w-md text-mist">@yield('message')</p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ url('/') }}" class="btn btn-primary btn-on-dark">{{ __('site.errors.back_home') }}</a>

                    {{-- The language switch on an error page cannot use
                         route_in_locale(): there is no matched route to
                         resolve a counterpart for. The homepage in the
                         other locale is the honest fallback. --}}
                    <a
                        href="{{ app()->getLocale() === 'ar' ? url('/en') : url('/') }}"
                        hreflang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                        rel="alternate"
                        class="btn btn-ghost-light btn-on-dark"
                    >
                        {{ app()->getLocale() === 'ar' ? __('site.nav.switch_to_english') : __('site.nav.switch_to_arabic') }}
                    </a>
                </div>
            </div>
        </main>
    </body>
</html>
