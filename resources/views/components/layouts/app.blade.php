@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#123C54">

        {{-- The dashboard must never be indexed. This is belt-and-braces
             alongside robots.txt: a Disallow is only a hint, whereas
             noindex is honoured even when a page is reached by a direct
             link. The routes are auth-gated regardless. --}}
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? __('dashboard.title') }} — {{ __('site.brand.name') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800,900&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        {{-- z-[70] keeps this above the mobile drawer panel (z-50) in every
             state, so it stays reachable while the drawer is open. --}}
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-petrol focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white focus:outline-none focus:ring-2 focus:ring-amber"
        >
            {{ __('dashboard.topbar.skip_to_content') }}
        </a>

        {{--
            The shell height is set in app.css (100vh with a 100dvh
            override) rather than with h-screen/h-dvh here: Tailwind emits
            .h-dvh BEFORE .h-screen, so the vh rule would win the cascade
            and the dvh class would silently do nothing on mobile.

            overflow-x-hidden on the scroll pane is the last line of
            defence against one wide child (a table, a long unbroken URL)
            making the whole dashboard scroll sideways on a phone.
        --}}
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="dashboard-shell flex overflow-hidden bg-cream">
            <x-dashboard.sidebar />

            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto overflow-x-hidden">
                <header class="sticky top-0 z-20 flex h-[72px] shrink-0 items-center border-b border-line bg-white/90 px-4 backdrop-blur-lg sm:px-6 lg:px-8">
                    <button
                        type="button"
                        @click="sidebarOpen = true"
                        class="-ms-2 inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-muted-ink transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol lg:hidden"
                        aria-label="{{ __('dashboard.topbar.open_menu') }}"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" />
                        </svg>
                    </button>

                    {{--
                        `ms-auto`, NOT `justify-between` on the header. The
                        hamburger above is lg:hidden, so at desktop widths
                        it leaves the box tree entirely and justify-between
                        would have a single flex item left — which collapses
                        to flex-start, i.e. the wrong side, and the SAME
                        physical side regardless of dir. ms-auto pushes this
                        cluster to the logical end unconditionally.
                    --}}
                    <div class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3">
                        <a
                            href="{{ route('home') }}"
                            class="hidden min-h-[44px] items-center rounded-lg border border-line px-3 text-sm font-bold text-petrol transition hover:border-petrol hover:bg-cream focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol sm:inline-flex"
                        >
                            {{ __('dashboard.nav.view_site') }}
                        </a>

                        <form method="POST" action="{{ route('dashboard.locale.update') }}">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-3 text-sm font-bold text-petrol transition hover:border-petrol hover:bg-cream focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                                aria-label="{{ __('dashboard.topbar.switch_language_label') }}"
                            >
                                {{ app()->getLocale() === 'ar' ? __('dashboard.topbar.switch_to_english') : __('dashboard.topbar.switch_to_arabic') }}
                            </button>
                        </form>

                        {{--
                            align="right" resolves to end-0, matching the
                            edge this trigger sits on. Anchoring the panel's
                            START edge here would throw a 12rem panel
                            off-screen at 375px — off the right in ltr, off
                            the left in rtl.
                        --}}
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button
                                    type="button"
                                    :aria-expanded="open ? 'true' : 'false'"
                                    aria-haspopup="true"
                                    aria-label="{{ __('dashboard.topbar.account_menu') }}"
                                    class="flex min-h-[44px] max-w-[12rem] items-center gap-2.5 rounded-full ps-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                                >
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-petrol text-sm font-bold text-white">
                                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                                    </span>
                                    <span class="hidden truncate text-sm font-bold text-ink sm:block">
                                        {{ auth()->user()->name }}
                                    </span>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('dashboard.topbar.profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link
                                        :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();"
                                    >
                                        {{ __('dashboard.auth.logout') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                @if ($header)
                    <div class="min-w-0 border-b border-line bg-white px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                @endif

                {{-- tabindex="-1" so the skip link moves focus here, not
                     merely scrolls to it. --}}
                <main id="main-content" tabindex="-1" class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    <x-dashboard.flash />

                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
