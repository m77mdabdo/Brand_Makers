@props(['enabled' => true])

{{--
    The cookie consent banner.

    WHY IT EXISTS. VisitorTrackingGate treats an absent decision as
    refusal, so without somewhere to grant consent the feature would
    correctly record nothing, forever.

    WHAT ACCEPTING COVERS, stated plainly so nobody assumes more or less
    than is true:
      - the visitor-session journey recording described in the privacy
        policy, and
      - the marketing tags in components/tracking-scripts.blade.php —
        Google via Consent Mode v2, and Meta and TikTok, which are not
        fetched at all until this banner is accepted.
    The two are gated by the SAME cookie but by different methods on the
    gate, so the admin switch below can turn journey recording off without
    silently disabling advertising.

    DECLINE CARRIES EQUAL WEIGHT. It comes first in the DOM, it is the same
    size, and it is a real button that stores a real answer — a banner
    where refusing is harder than accepting is not consent. Ignoring the
    banner entirely produces exactly the same server behaviour as
    declining.

    NOT RENDERED AT ALL when journey recording is switched off in Settings:
    the marketing tags are the only thing left to ask about, and a site
    with no tracking IDs configured would be asking permission for nothing.

    z-40: above the back-to-top button (z-30) so it cannot be obscured,
    below the mobile drawer panel (z-50) so it never covers an open menu.
--}}
@if ($enabled)
    <div
        x-data="cookieConsent"
        x-show="! decided"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        role="region"
        aria-label="{{ __('site.consent.aria_label') }}"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-cream/95 p-4 shadow-2xl backdrop-blur sm:p-5"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-relaxed text-muted-ink">
                {{ __('site.consent.message') }}
                <a
                    href="{{ lroute('pages.privacy-policy') }}"
                    class="font-bold text-orange-ink underline underline-offset-2 hover:text-petrol focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-ink"
                >
                    {{ __('site.consent.policy_link') }}
                </a>
            </p>

            <div class="flex shrink-0 flex-wrap gap-3">
                <button
                    type="button"
                    @click="decline()"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-line px-5 text-sm font-bold text-ink transition hover:border-petrol hover:text-petrol focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                >
                    {{ __('site.consent.decline') }}
                </button>

                <button
                    type="button"
                    @click="accept()"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-petrol px-5 text-sm font-bold text-white transition hover:bg-petrol-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol focus-visible:ring-offset-2"
                >
                    {{ __('site.consent.accept') }}
                </button>
            </div>
        </div>
    </div>
@endif
