@props([
    // SEO inputs. DECLARED, not read off the parent scope: a Blade
    // component has its own variable scope, so `$seoTitle ?? null` inside
    // an undeclared component silently resolves to null and every page
    // quietly falls back to the site-wide default title.
    'seoModel' => null,
    'seoTitle' => null,
    'seoDescription' => null,
    'seoType' => 'website',
])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#123C54">

        {{-- <title>, description, canonical, Open Graph and Twitter card.
             Values resolve through SeoTagService: the record's own SeoMeta
             row first, then what the view passed, then the record's own
             content, then the seo_defaults settings. --}}
        <x-seo-tags
            :model="$seoModel"
            :title="$seoTitle"
            :description="$seoDescription"
            :type="$seoType"
        />

        {{-- Favicons. sizes="any" on the .ico stops a browser that also
             sees the 32px PNG from downloading both. --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        {{--
            hreflang alternates. Present only when the current route has a
            dual-registered variant (see route_in_locale() in
            app/helpers.php) — every public marketing route does, so this
            covers the whole public site and nothing else.

            x-default points at the Arabic (no-prefix) URL because that is
            this site's actual default for a visitor signalling no language
            preference.
        --}}
        @if ($hreflangAr = route_in_locale('ar'))
            <link rel="alternate" hreflang="ar" href="{{ $hreflangAr }}">
            <link rel="alternate" hreflang="x-default" href="{{ $hreflangAr }}">
        @endif
        @if ($hreflangEn = route_in_locale('en'))
            <link rel="alternate" hreflang="en" href="{{ $hreflangEn }}">
        @endif

        {{-- Tajawal, 400–900. bunny.net rather than Google Fonts: same
             files, no per-visitor request to an ad network, which is what
             lets the privacy policy say what it says. --}}
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800,900&display=swap" rel="stylesheet">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans">
        {{-- Visually hidden until focused. The homepage puts a hero, a
             marquee and a navbar in front of the content, which is a long
             walk for a keyboard user. WCAG 2.4.1. --}}
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-petrol focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white focus:outline-none focus:ring-2 focus:ring-amber"
        >
            {{ __('site.nav.skip_to_content') }}
        </a>

        <x-public.navbar :menu="$menu" :site-name="$siteName" />

        {{-- tabindex="-1" so the skip link actually moves focus here;
             without it the browser scrolls but focus stays on the link. --}}
        <main id="main-content" tabindex="-1">
            {{ $slot }}
        </main>

        <x-public.footer
            :site-name="$siteName"
            :social-links="$socialLinks"
            :contact-email="$contactEmail"
            :contact-phone="$contactPhone"
            :contact-address="$contactAddress"
            :contact-whatsapp="$contactWhatsapp"
        />

        <x-public.back-to-top />
    </body>
</html>
