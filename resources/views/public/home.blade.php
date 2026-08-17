{{--
    The homepage.

    Section order follows the agreed flow:
    hero → trust marquee → services → estimator teaser → packages →
    order-tracker teaser → booking CTA → footer.

    Every section reads from the database. A section whose data is empty
    renders nothing at all rather than an empty heading — which is the
    normal state for testimonials on a production install, since those are
    demo-only data.
--}}
<x-layouts.public :seo-title="$seoTitle" :seo-description="$seoDescription">
    {{-- The hero is swappable to real footage with no layout change:
         <x-public.hero :video="asset('video/riyadh-dusk.mp4')" /> --}}
    <x-public.hero />

    {{-- Trust marquee. Pulls the real service names, so it can never
         advertise something the catalogue does not offer. --}}
    @if ($services->isNotEmpty())
        <x-public.marquee :items="$services->map->name" />
    @endif


    {{-- ------------------------------------------------------------- --}}
    {{-- Value props                                                     --}}
    {{-- ------------------------------------------------------------- --}}
    {{-- Fixed copy from lang, not the database: these four are a
         positioning statement rather than a catalogue, and they sit with
         the hero they answer. --}}
    <section id="values" class="section scroll-mt-24 pb-0">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow eyebrow-light">{{ __('site.values.eyebrow') }}</span>
                <h2>{{ __('site.values.title') }}</h2>
            </div>

            <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('site.values.items') as $value)
                    <li class="rounded-2xl border border-line bg-white p-6">
                        <h3 class="text-lg font-black text-ink">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-ink">{{ $value['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ------------------------------------------------------------- --}}
    {{-- Activities                                                      --}}
    {{-- ------------------------------------------------------------- --}}
    {{-- The same PageSection rows the /activities page renders, so the
         two can never disagree. Absent when the page is unpublished. --}}
    @if ($activities->isNotEmpty())
        <section id="activities" class="section scroll-mt-24">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.activities.eyebrow') }}</span>
                    <h2>{{ __('site.activities.title') }}</h2>
                    <p>{{ __('site.activities.lead') }}</p>
                </div>

                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($activities as $activity)
                        <li class="flex h-full flex-col rounded-2xl border border-line bg-white p-6">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cream text-petrol" aria-hidden="true">
                                <x-public.service-icon :name="$activity->icon" />
                            </span>
                            <h3 class="mt-4 text-lg font-black text-ink">{{ $activity->title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-ink">{{ $activity->description }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8 text-center">
                    <a href="{{ lroute('pages.activities') }}" class="btn btn-ghost-dark">{{ __('site.activities.view_all') }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Services                                                        --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($services->isNotEmpty())
        <section id="services" class="section scroll-mt-24">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.services.eyebrow') }}</span>
                    <h2>{{ __('site.services.title') }}</h2>
                    <p>{{ __('site.services.lead') }}</p>
                </div>

                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($services as $service)
                        <li class="card flex flex-col">
                            <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-petrol text-white" aria-hidden="true">
                                <x-public.service-icon :name="$service->icon" />
                            </span>
                            <h3 class="text-lg font-extrabold">{{ $service->name }}</h3>
                            <p class="mt-2 text-sm text-muted-ink">{{ $service->summary }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Cost estimator teaser                                           --}}
    {{-- ------------------------------------------------------------- --}}
    <section id="estimator" class="section scroll-mt-24 pt-0">
        <div class="wrap">
            <div class="grid items-center gap-10 rounded-[2rem] bg-petrol p-8 text-white sm:p-12 lg:grid-cols-2">
                <div>
                    <span class="eyebrow eyebrow-dark">{{ __('site.home_estimator.eyebrow') }}</span>
                    <h2 class="text-3xl font-black leading-tight sm:text-4xl">{{ __('site.home_estimator.title') }}</h2>
                    <p class="mt-4 text-mist">{{ __('site.home_estimator.lead') }}</p>
                </div>

                <div class="rounded-3xl bg-petrol-2 p-7">
                    <p class="text-sm font-bold uppercase tracking-wide text-mist-dim">{{ __('site.home_estimator.panel_title') }}</p>

                    @if ($estimatorRange)
                        {{-- A REAL range from priced, active items — never
                             an invented figure. --}}
                        <p class="mt-4 text-sm text-mist">{{ __('site.home_estimator.total_label') }}</p>
                        <p class="mt-1 text-3xl font-black text-amber sm:text-4xl" dir="ltr">
                            {{ number_format($estimatorRange['min'], 0) }}
                            <span class="text-mist-dim">–</span>
                            {{ number_format($estimatorRange['max'], 0) }}
                            <span class="text-xl text-mist">{{ __('site.home_estimator.currency') }}</span>
                        </p>
                        <p class="mt-4 text-xs leading-relaxed text-mist-dim">{{ __('site.home_estimator.disclaimer') }}</p>
                    @else
                        {{-- Nothing has been priced yet (EstimatorItemSeeder
                             ships its catalogue inactive on purpose), so the
                             teaser says so instead of quoting a number the
                             studio never agreed. --}}
                        <p class="mt-4 text-2xl font-black text-amber">{{ __('site.home_estimator.unpriced') }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-mist">{{ __('site.home_estimator.unpriced_hint') }}</p>
                    @endif

                    <a href="{{ lroute('estimator') }}" class="btn btn-primary btn-on-dark mt-6 w-full">{{ __('site.home_estimator.cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Second marquee, running AGAINST the first. Adjacent bands shearing
         past each other is the effect; a page of parallel rows is not. --}}
    @if ($services->isNotEmpty())
        <x-public.marquee :items="$services->map->name" direction="reverse" tone="petrol" />
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Packages                                                        --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($packages->isNotEmpty())
        <section id="packages" class="section scroll-mt-24">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.packages.eyebrow') }}</span>
                    <h2>{{ __('site.packages.title') }}</h2>
                    <p>{{ __('site.packages.lead') }}</p>
                </div>

                {{-- items-start so the popular card's scale-up does not
                     stretch its neighbours. --}}
                <ul class="grid items-start gap-6 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($packages as $package)
                        <li @class([
                            'relative flex h-full flex-col rounded-[1.5rem] border p-8',
                            'border-petrol bg-petrol text-white shadow-[0_30px_60px_-24px_rgba(10,37,52,0.5)] lg:-my-2 lg:py-10' => $package->is_popular,
                            'border-line bg-white' => ! $package->is_popular,
                        ])>
                            @if ($package->is_popular)
                                <span class="absolute -top-3 end-6 rounded-full bg-orange-ink px-4 py-1 text-xs font-extrabold text-white">
                                    {{ __('site.packages.popular') }}
                                </span>
                            @endif

                            <h3 @class(['text-xl font-black', 'text-white' => $package->is_popular])>{{ $package->name }}</h3>

                            <p class="mt-3">
                                @if ($package->isPriceOnRequest())
                                    {{-- price = null means "on request", which
                                         is different from free. See PackageSeeder. --}}
                                    <span @class([
                                        'text-2xl font-black',
                                        'text-amber' => $package->is_popular,
                                        'text-orange-ink' => ! $package->is_popular,
                                    ])>{{ __('site.packages.price_on_request') }}</span>
                                @else
                                    <span @class([
                                        'text-4xl font-black',
                                        'text-amber' => $package->is_popular,
                                        'text-orange-ink' => ! $package->is_popular,
                                    ]) dir="ltr">{{ number_format((float) $package->price, 0) }}</span>
                                    <span @class([
                                        'text-sm font-medium',
                                        'text-mist-dim' => $package->is_popular,
                                        'text-muted-ink' => ! $package->is_popular,
                                    ])>{{ __('site.packages.currency') }}</span>
                                @endif
                            </p>

                            @if ($package->description)
                                <p @class([
                                    'mt-3 text-sm',
                                    'text-mist' => $package->is_popular,
                                    'text-muted-ink' => ! $package->is_popular,
                                ])>{{ $package->description }}</p>
                            @endif

                            <ul class="mt-5 flex-1 space-y-2.5 text-sm">
                                @foreach ($package->featureList() as $feature)
                                    <li class="flex gap-2.5">
                                        <span @class([
                                            'font-black',
                                            'text-amber' => $package->is_popular,
                                            'text-orange-ink' => ! $package->is_popular,
                                        ]) aria-hidden="true">&check;</span>
                                        <span @class(['text-mist' => $package->is_popular])>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <a
                                href="{{ lroute('packages.index') }}#package-enquiry"
                                @class([
                                    'btn mt-6 w-full',
                                    'btn-white btn-on-dark' => $package->is_popular,
                                    'btn-ghost-dark' => ! $package->is_popular,
                                ])
                            >
                                {{ __('site.packages.choose') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Order tracker teaser                                            --}}
    {{-- ------------------------------------------------------------- --}}
    <section id="tracker" class="section scroll-mt-24 pt-0">
        <div class="wrap">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <span class="eyebrow eyebrow-light">{{ __('site.home_tracker.eyebrow') }}</span>
                    <h2 class="text-3xl font-black tracking-tight sm:text-4xl">{{ __('site.home_tracker.title') }}</h2>
                    <p class="mt-3 text-muted-ink">{{ __('site.home_tracker.lead') }}</p>

                    <ol class="mt-6">
                        @foreach (__('site.home_tracker.steps') as $index => $step)
                            <li class="flex gap-4 border-b border-dashed border-line py-4 last:border-0">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-petrol font-black text-white" aria-hidden="true">
                                    {{ $index + 1 }}
                                </span>
                                <span>
                                    <span class="block font-extrabold">{{ $step['title'] }}</span>
                                    <span class="block text-sm text-muted-ink">{{ $step['body'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- An ILLUSTRATION of the tracking screen, labelled as
                     one. It carries no order reference that could be
                     mistaken for real, and the caption says what it is —
                     a mocked-up progress bar presented as live data would
                     be a fabricated record. --}}
                <figure class="rounded-3xl border border-line bg-white p-7">
                    <div class="flex items-center justify-between gap-3">
                        <b class="font-extrabold" dir="ltr">{{ __('site.home_tracker.sample_reference') }}</b>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-extrabold text-orange-ink ring-1 ring-inset ring-line">
                            {{ __('site.home_tracker.sample_status') }}
                        </span>
                    </div>

                    <div
                        class="my-4 h-2.5 overflow-hidden rounded-full bg-cream-2"
                        role="img"
                        aria-label="{{ __('site.home_tracker.progress_label') }}: 66%"
                    >
                        <div class="h-full w-2/3 rounded-full bg-orange-ink"></div>
                    </div>

                    <div class="flex justify-between text-xs text-muted-ink">
                        @foreach (__('site.home_tracker.steps') as $step)
                            <span>{{ $step['title'] }}</span>
                        @endforeach
                    </div>

                    <figcaption class="mt-5 text-sm text-muted-ink">{{ __('site.home_tracker.sample_note') }}</figcaption>
                </figure>
            </div>
        </div>
    </section>


    {{-- ------------------------------------------------------------- --}}
    {{-- Why Saudi Arabia                                                --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($whySaudi->isNotEmpty())
        <section id="why-saudi" class="section scroll-mt-24 pt-0">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.why_saudi.eyebrow') }}</span>
                    <h2>{{ __('site.why_saudi.title') }}</h2>
                </div>

                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($whySaudi as $reason)
                        <li class="rounded-2xl border border-line bg-cream-2/40 p-6">
                            <h3 class="text-base font-black text-ink">{{ $reason->title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted-ink">{{ $reason->description }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8 text-center">
                    <a href="{{ lroute('pages.why-saudi') }}" class="btn btn-ghost-dark">{{ __('site.why_saudi.view_all') }}</a>
                </div>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Testimonials — demo-only data, so normally absent               --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($testimonials->isNotEmpty())
        <section id="testimonials" class="section scroll-mt-24 bg-cream-2/50 pt-16">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.testimonials.eyebrow') }}</span>
                    <h2>{{ __('site.testimonials.title') }}</h2>
                </div>

                <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($testimonials as $testimonial)
                        <li class="flex h-full flex-col rounded-3xl border border-line bg-white p-6">
                            <blockquote class="flex-1 text-sm leading-relaxed text-muted-ink">
                                &ldquo;{{ $testimonial->quote }}&rdquo;
                            </blockquote>
                            <p class="mt-4 font-extrabold">{{ $testimonial->client_name }}</p>
                            @if ($testimonial->client_title)
                                <p class="text-xs text-muted-ink">{{ $testimonial->client_title }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- FAQs                                                            --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="section scroll-mt-24">
            <div class="wrap max-w-3xl">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.faq.eyebrow') }}</span>
                    <h2>{{ __('site.faq.title') }}</h2>
                </div>

                {{-- Native <details>, not an Alpine accordion: it is
                     keyboard-accessible, announced correctly and fully
                     functional with JavaScript disabled, for no code. --}}
                <ul class="space-y-3">
                    @foreach ($faqs as $faq)
                        <li>
                            <details class="group rounded-2xl border border-line bg-white px-5 py-4 [&_summary::-webkit-details-marker]:hidden">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 font-extrabold focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol">
                                    {{ $faq->question }}
                                    <svg class="h-5 w-5 shrink-0 text-orange-ink transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M6 9l6 6 6-6" stroke-linecap="round" />
                                    </svg>
                                </summary>
                                <p class="mt-3 text-sm leading-relaxed text-muted-ink">{{ $faq->answer }}</p>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Articles                                                        --}}
    {{-- ------------------------------------------------------------- --}}
    @if ($articles->isNotEmpty())
        <section id="articles" class="section scroll-mt-24 pt-0">
            <div class="wrap">
                <div class="sec-head">
                    <span class="eyebrow eyebrow-light">{{ __('site.articles.eyebrow') }}</span>
                    <h2>{{ __('site.articles.title') }}</h2>
                </div>

                <ul class="grid gap-5 md:grid-cols-3">
                    @foreach ($articles as $article)
                        <li class="card flex h-full flex-col">
                            <time class="text-xs font-bold uppercase tracking-wide text-muted-ink" datetime="{{ $article->published_at?->toDateString() }}">
                                {{ $article->published_at?->translatedFormat('j F Y') }}
                            </time>
                            <h3 class="mt-2 text-lg font-extrabold">{{ $article->title }}</h3>
                            @if ($article->excerpt)
                                <p class="mt-2 flex-1 text-sm text-muted-ink">{{ $article->excerpt }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------- --}}
    {{-- Booking CTA                                                     --}}
    {{-- ------------------------------------------------------------- --}}
    <section id="booking" class="section scroll-mt-24 pt-0">
        <div class="wrap">
            <div class="rounded-[2rem] bg-[linear-gradient(120deg,#B84A15,#8C370D)] p-10 text-center text-white sm:p-14">
                <h2 class="text-3xl font-black sm:text-4xl">{{ __('site.home_booking.title') }}</h2>
                <p class="mx-auto mt-4 max-w-lg text-lg text-white/95">{{ __('site.home_booking.lead') }}</p>
                <a href="{{ lroute('booking') }}" class="btn btn-white btn-on-dark mt-7">{{ __('site.home_booking.cta') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
