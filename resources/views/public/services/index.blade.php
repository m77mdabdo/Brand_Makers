<x-layouts.public :seo-title="__('site.services.label')" :seo-description="__('site.services.lead')">
    <x-public.page-hero
        :eyebrow="__('site.services.eyebrow')"
        :title="__('site.services.title')"
        :lead="__('site.services.lead')"
    />

    <section class="section">
        <div class="wrap">
            @if ($services->isEmpty())
                <p class="text-center text-muted-ink">{{ __('site.services.empty') }}</p>
            @else
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <li>
                            {{-- The whole card is the link, so the touch
                                 target is the card rather than a 14px
                                 "read more" at the bottom of it. --}}
                            <a href="{{ lroute('services.show', $service) }}" class="card flex h-full flex-col focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-petrol text-white" aria-hidden="true">
                                    <x-public.service-icon :name="$service->icon" />
                                </span>
                                <h2 class="text-lg font-extrabold">{{ $service->name }}</h2>
                                <p class="mt-2 flex-1 text-sm text-muted-ink">{{ $service->summary }}</p>

                                {{-- null is "على الطلب", never a zero: a
                                     service nobody has priced is still
                                     offered, and printing 0 would say it
                                     is free. --}}
                                <p class="mt-4 text-sm font-black {{ $service->isPriceOnRequest() ? 'text-muted-ink' : 'text-orange-ink' }}" @if (! $service->isPriceOnRequest()) dir="ltr" @endif>
                                    @if ($service->isPriceOnRequest())
                                        {{ __('site.services.on_request') }}
                                    @else
                                        {{ number_format((float) $service->price, 0) }} {{ __('site.services.currency') }}
                                    @endif
                                </p>

                                <span class="mt-2 text-sm font-bold text-orange-ink">{{ __('site.services.read_more') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <x-public.cta-band />
</x-layouts.public>
