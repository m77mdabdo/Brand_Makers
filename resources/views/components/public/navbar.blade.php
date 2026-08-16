@props(['menu', 'siteName'])

@php
    /*
     * The booking CTA is the ONLY thing in the bar pointing at /book —
     * there is no menu row for it — so nothing else can carry the current
     * -page marker on the booking page. Without this, a screen-reader user
     * on /book hears no indication of where they are.
     *
     * The base name, so the marker is identical on /book and /en/book.
     */
    $bookingIsCurrent = current_route_base_name() === 'booking';
@endphp

{{--
    Translucent, blurred, sticky.

    The blur is `supports-[backdrop-filter]` gated: browsers without
    backdrop-filter get the same bar at a HIGHER opacity instead, because
    a translucent bar with no blur behind it makes the links unreadable
    over the hero. The fallback is the safe state, the blur is the
    enhancement.

    z-40 sits above page content and the floating buttons (z-30) but below
    the mobile drawer panel (z-50), so the drawer is never fought by the
    bar that opened it.
--}}
<header
    x-data="{ scrolled: false, mobileOpen: false }"
    @scroll.window.passive="scrolled = window.scrollY > 24"
    @keydown.escape.window="mobileOpen = false"
    :class="scrolled ? 'border-line shadow-[0_4px_24px_-16px_rgba(10,37,52,0.5)]' : 'border-transparent'"
    class="sticky top-0 z-40 border-b bg-cream/90 backdrop-blur-none transition-colors duration-300 supports-[backdrop-filter]:bg-cream/70 supports-[backdrop-filter]:backdrop-blur-xl"
>
    <div class="wrap">
        <div class="flex h-[72px] items-center justify-between gap-4 sm:h-[88px]">
            {{--
                logo-dark is the dark-ink lockup, for use ON the light
                cream bar; the footer uses logo-light on petrol.

                It is a STACKED lockup (mark above an Arabic wordmark) at
                530x363, which is why the bar is 88px tall rather than the
                usual ~64px: at any smaller height the wordmark under the
                mark stops being legible and the logo reads as a smudge.
                The intrinsic dimensions below are the file's real ones —
                declaring a wide-lockup ratio here would reserve the wrong
                box and shift the whole bar when the image lands.
            --}}
            <a href="{{ lroute('home') }}" class="shrink-0" aria-label="{{ $siteName }}">
                <img
                    src="{{ asset('logo-dark.png') }}"
                    alt="{{ $siteName }}"
                    width="530"
                    height="363"
                    {{-- Deliberately NOT lazy: above the fold on every
                         page, so lazy-loading it only delays first paint. --}}
                    class="h-12 w-auto sm:h-16"
                >
            </a>

            {{-- Rendered from menu_items, not hardcoded. An item whose
                 route is not registered yet resolves to no href, and
                 x-public.nav-item drops it rather than rendering a dead
                 link — so each later build step lights up its own entry
                 automatically. --}}
            <nav class="hidden items-center gap-6 text-[0.95rem] font-medium lg:flex" aria-label="{{ __('site.nav.menu_dialog_label') }}">
                @forelse ($menu as $item)
                    <x-public.nav-item :item="$item" />
                @empty
                    {{-- Empty-menu fallback. The navbar is rendered from
                         menu_items, so an admin who hid every entry would
                         otherwise ship a bar with no navigation at all. --}}
                    <a href="{{ lroute('home') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-petrol hover:text-orange-ink">
                        {{ __('site.nav.home') }}
                    </a>
                @endforelse
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                <a
                    href="{{ route_in_locale(app()->getLocale() === 'ar' ? 'en' : 'ar') ?? lroute('home') }}"
                    class="inline-flex min-h-[44px] items-center rounded-full border border-line px-3.5 text-sm font-bold text-petrol transition hover:border-petrol hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                    aria-label="{{ __('site.nav.switch_language_label') }}"
                    {{-- hreflang + rel=alternate make the toggle a machine-
                         readable language link, not just a styled anchor. --}}
                    hreflang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"
                    rel="alternate"
                >
                    {{ app()->getLocale() === 'ar' ? __('site.nav.switch_to_english') : __('site.nav.switch_to_arabic') }}
                </a>

                {{--
                    THE ONE booking call to action in the bar. There is no
                    menu row for it — a plain link and a CTA button to the
                    same page put the same ask twice in one bar, competing
                    for the same click. See MenuItemSeeder::items().

                    lroute('booking'), not "#booking": the bar renders on
                    every page, and a fragment pointing at a homepage
                    section did nothing at all from the other thirty.
                --}}
                <a
                    href="{{ lroute('booking') }}"
                    @if ($bookingIsCurrent) aria-current="page" @endif
                    class="btn btn-primary hidden !px-5 !py-2.5 !text-sm sm:inline-flex"
                >
                    {{ __('site.nav.book_consultation') }}
                </a>

                <button
                    type="button"
                    @click="mobileOpen = true"
                    class="-me-2 inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-petrol transition hover:text-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol lg:hidden"
                    aria-label="{{ __('site.nav.open_menu') }}"
                >
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile drawer -->
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.opacity
        @click="mobileOpen = false"
        class="fixed inset-0 z-40 bg-petrol-deep/50 lg:hidden"
    ></div>

    {{--
        x-trap.noscroll holds focus inside the panel while it is open,
        returns it to the hamburger on close, and stops the page behind it
        scrolling under the finger.

        The panel slides in from the INLINE end (`end-0` + a translate that
        flips with direction), so it enters from the right in Arabic and
        the left in English — matching where the hamburger that opened it
        sits in each direction.
    --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-trap.noscroll="mobileOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('site.nav.menu_dialog_label') }}"
        class="fixed inset-x-0 top-0 z-50 mx-3 mt-3 max-h-[90vh] overflow-y-auto rounded-3xl border border-line bg-cream p-5 shadow-2xl lg:hidden"
    >
        <div class="mb-4 flex items-center justify-between">
            <img src="{{ asset('logo-dark.png') }}" alt="{{ $siteName }}" width="530" height="363" loading="lazy" class="h-12 w-auto">
            <button
                type="button"
                @click="mobileOpen = false"
                class="-me-2 inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-muted-ink transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                aria-label="{{ __('site.nav.close_menu') }}"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M6 6l12 12M6 18L18 6" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        {{-- Same component as the desktop bar, in mobile mode: one
             source of truth for the active state, the dropdown behaviour
             and the drop-unresolvable-items rule. A second hand-written
             loop here is how the two quietly diverge. --}}
        <nav class="space-y-1 text-base font-medium" aria-label="{{ __('site.nav.menu_dialog_label') }}">
            @forelse ($menu as $item)
                <x-public.nav-item :item="$item" mobile />
            @empty
                {{-- Empty-menu fallback: an admin who hides or deletes
                     every item must still leave a way home rather than a
                     drawer with nothing in it. --}}
                <a href="{{ lroute('home') }}" class="flex min-h-[44px] items-center rounded-lg px-3 text-petrol">
                    {{ __('site.nav.home') }}
                </a>
            @endforelse
        </nav>

        {{-- The drawer's copy of the CTA, last, after the menu — same
             destination and same "only once" rule as the desktop bar. --}}
        <a
            href="{{ lroute('booking') }}"
            @click="mobileOpen = false"
            @if ($bookingIsCurrent) aria-current="page" @endif
            class="btn btn-primary mt-5 w-full"
        >
            {{ __('site.nav.book_consultation') }}
        </a>
    </div>
</header>
