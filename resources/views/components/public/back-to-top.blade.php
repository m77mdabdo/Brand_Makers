{{--
    POSITIONING NOTE. This uses the literal `left-6`, not the logical
    `start-6`, on purpose: it is pinned to one PHYSICAL corner so it does
    not jump across the screen when the language toggle flips direction.
    A floating utility that changes corners between locales reads as a
    bug, not as localisation.

    z-30 keeps it above page content but below the mobile drawer's
    backdrop (z-40) and panel (z-50), so it never floats over an open
    menu.
--}}
<div
    x-data="{
        shown: false,
        onScroll() { this.shown = window.scrollY > 350 },
        toTop() {
            /* An instant jump for anyone who asked for less motion — a
               long smooth scroll is exactly what that preference is
               about. */
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
        },
    }"
    x-init="onScroll()"
    @scroll.window.passive="onScroll()"
>
    <button
        type="button"
        x-show="shown"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        @click="toTop()"
        aria-label="{{ __('site.nav.back_to_top') }}"
        class="fixed bottom-6 left-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-petrol text-white shadow-lg transition hover:scale-105 hover:bg-orange-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol focus-visible:ring-offset-2 focus-visible:ring-offset-cream"
    >
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5" />
            <path d="M5 12l7-7 7 7" />
        </svg>
    </button>
</div>
