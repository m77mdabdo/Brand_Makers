@props([
    'siteName',
    'socialLinks' => [],
    'contactEmail' => null,
    'contactPhone' => null,
    'contactAddress' => null,
    'contactWhatsapp' => null,
])

{{--
    pb-28 rather than a symmetric pb-12: the back-to-top button is fixed
    at the bottom of the viewport, so without the extra clearance the
    copyright line — the last thing on every page — sits underneath it.
--}}
<footer class="bg-petrol-deep text-mist-dim">
    <div class="wrap pb-28 pt-16">
        <div class="grid gap-10 border-b border-white/10 pb-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2 lg:max-w-sm">
                {{-- logo-light is the reversed lockup, for petrol. Same
                     stacked 530x363 artwork as the navbar's, given more
                     height here because the footer has the room. --}}
                <img
                    src="{{ asset('logo-light.png') }}"
                    alt="{{ $siteName }}"
                    width="530"
                    height="363"
                    loading="lazy"
                    class="h-20 w-auto"
                >
                <p class="mt-4 text-sm leading-relaxed">{{ __('site.footer.about') }}</p>

                @if ($socialLinks)
                    <ul class="mt-5 flex flex-wrap gap-2">
                        @foreach ($socialLinks as $platform => $url)
                            <li>
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-full border border-white/15 px-4 text-sm font-bold text-mist transition hover:border-amber hover:text-amber focus:outline-none focus-visible:ring-2 focus-visible:ring-amber"
                                >
                                    {{ ucfirst($platform) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <nav aria-labelledby="footer-links-heading">
                <h2 id="footer-links-heading" class="mb-3 font-extrabold text-white">{{ __('site.footer.links_heading') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ lroute('home') }}" class="transition hover:text-amber">{{ __('site.nav.home') }}</a></li>
                    <li><a href="{{ lroute('pages.about') }}" class="transition hover:text-amber">{{ __('site.footer.links_about') }}</a></li>
                    <li><a href="{{ lroute('pages.our-process') }}" class="transition hover:text-amber">{{ __('site.footer.links_process') }}</a></li>
                    <li><a href="{{ lroute('home') }}#packages" class="transition hover:text-amber">{{ __('site.packages.eyebrow') }}</a></li>
                </ul>
            </nav>

            <div>
                {{--
                    The contact settings ship EMPTY rather than with
                    placeholder text, so on an unconfigured install there
                    is nothing to list. The HEADING is therefore
                    conditional too — a "Contact" heading with nothing
                    under it reads as a broken page, which is worse than
                    the section simply not being there yet.
                --}}
                @if ($contactWhatsapp || $contactPhone || $contactEmail || $contactAddress)
                    <h2 class="mb-3 font-extrabold text-white">{{ __('site.footer.contact_heading') }}</h2>
                    <ul class="space-y-2 text-sm">
                    @if ($contactWhatsapp)
                        <li>
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $contactWhatsapp) }}" target="_blank" rel="noopener noreferrer" class="transition hover:text-amber">
                                WhatsApp
                            </a>
                        </li>
                    @endif
                    @if ($contactPhone)
                        <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $contactPhone) }}" dir="ltr" class="inline-block transition hover:text-amber">{{ $contactPhone }}</a></li>
                    @endif
                    @if ($contactEmail)
                        <li><a href="mailto:{{ $contactEmail }}" dir="ltr" class="inline-block transition hover:text-amber">{{ $contactEmail }}</a></li>
                    @endif
                    @if ($contactAddress)
                        <li>{{ $contactAddress }}</li>
                    @endif
                    </ul>
                @endif

                <h2 class="mb-3 font-extrabold text-white [&:not(:first-child)]:mt-6">{{ __('site.footer.legal_heading') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ lroute('pages.privacy-policy') }}" class="underline transition hover:text-amber">{{ __('site.footer.privacy_link') }}</a></li>
                    <li><a href="{{ lroute('pages.terms-and-conditions') }}" class="underline transition hover:text-amber">{{ __('site.footer.terms_link') }}</a></li>
                </ul>
            </div>
        </div>

        <p class="pt-6 text-center text-sm">
            &copy; {{ now()->year }} {{ $siteName }}. {{ __('site.footer.rights') }}
        </p>
    </div>
</footer>
