@php
    /**
     * The catalogue handed to Alpine.
     *
     * A data attribute rather than an inline <script> block: the page then
     * carries no executable JSON, and Blade's normal attribute escaping is
     * doing the work rather than a hand-rolled JSON-in-JS encoder.
     *
     * Prices go out as the decimal strings the database holds. The client
     * converts them to integer minor units before adding anything — see
     * resources/js/estimator.js for why floats are not summed here.
     */
    $catalogue = [
        'locale' => app()->getLocale(),
        'items' => $groups->flatten()->mapWithKeys(fn ($item) => [
            $item->key => ['label' => $item->label, 'price' => (string) $item->price],
        ])->all(),
        'packages' => $packages->mapWithKeys(fn ($package) => [
            $package->slug => [
                'name' => $package->name,
                // null, not 0 — "on request" is a different fact from free.
                'price' => $package->isPriceOnRequest() ? null : (string) $package->price,
            ],
        ])->all(),
    ];

@endphp

<x-layouts.public :seo-title="__('site.estimator.title')" :seo-description="__('site.estimator.lead')">
    <x-public.page-hero
        :eyebrow="__('site.estimator.eyebrow')"
        :title="__('site.estimator.title')"
        :lead="__('site.estimator.lead')"
    />

    <x-public.breadcrumbs :items="[__('site.nav.home') => lroute('home'), __('site.estimator.label') => null]" />

    <section class="section" id="estimator">
        <div class="wrap">
            <x-public.flash />

            @if (! $hasPricing)
                {{--
                    Nothing is priced and activated yet.

                    The catalogue ships priced at zero and INACTIVE, so a
                    fresh install lands here rather than showing an
                    estimator that adds up to nothing. Saying the pricing is
                    being prepared and offering the contact route is honest;
                    a working calculator returning 0 SAR would not be.
                --}}
                <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-8 text-center">
                    <h2 class="text-xl font-black text-ink">{{ __('site.estimator.unpriced') }}</h2>
                    <p class="mt-3 text-sm text-muted-ink">{{ __('site.estimator.unpriced_hint') }}</p>
                    <a href="{{ lroute('contact') }}" class="btn btn-primary mt-6">{{ __('site.estimator.unpriced_cta') }}</a>
                </div>
            @else
                <div
                    x-data="costEstimator(window.estimatorConfig($el))"
                    data-estimator-catalogue="{{ json_encode($catalogue, JSON_UNESCAPED_UNICODE) }}"
                    class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start"
                >
                    {{-- ---------------------------------------------------
                         The picker
                    --------------------------------------------------- --}}
                    <div class="space-y-8">
                        @if ($packages->isNotEmpty())
                            <fieldset class="rounded-2xl border border-line bg-white p-6">
                                <legend class="px-2 text-lg font-black text-ink">{{ __('site.estimator.step_package') }}</legend>
                                <p class="mt-1 text-sm text-muted-ink">{{ __('site.estimator.step_package_help') }}</p>

                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    {{-- "No base package" first and selected
                                         by default: the estimator must be
                                         usable by someone who only wants a
                                         few add-ons, and defaulting to a
                                         package would put a number on the
                                         screen the visitor never chose. --}}
                                    <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-xl border border-line p-4 transition hover:border-petrol has-[:checked]:border-petrol has-[:checked]:bg-cream">
                                        <input type="radio" name="package" value="" x-model="selectedPackage" checked class="h-5 w-5 shrink-0 border-line text-orange-ink focus:ring-petrol">
                                        <span class="text-sm font-bold text-ink">{{ __('site.estimator.no_package') }}</span>
                                    </label>

                                    @foreach ($packages as $package)
                                        <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-xl border border-line p-4 transition hover:border-petrol has-[:checked]:border-petrol has-[:checked]:bg-cream">
                                            <input
                                                type="radio"
                                                name="package"
                                                value="{{ $package->slug }}"
                                                x-model="selectedPackage"
                                                class="mt-0.5 h-5 w-5 shrink-0 border-line text-orange-ink focus:ring-petrol"
                                            >
                                            <span class="min-w-0">
                                                <span class="block text-sm font-bold text-ink">{{ $package->name }}</span>
                                                @if ($package->isPriceOnRequest())
                                                    {{-- No number invented to fill the gap. --}}
                                                    <span class="mt-1 block text-xs font-bold text-muted-ink">{{ __('site.estimator.on_request') }}</span>
                                                @else
                                                    <span class="mt-1 block text-xs font-bold text-orange-ink" dir="ltr">
                                                        {{ number_format((float) $package->price, 0) }} {{ __('site.estimator.currency') }}
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        @foreach ($groups as $group => $items)
                            <fieldset class="rounded-2xl border border-line bg-white p-6">
                                <legend class="px-2 text-lg font-black text-ink">
                                    {{-- The group key is a stable slug; the
                                         heading is a translation of it, with
                                         the raw key as the fallback so a
                                         group added by an admin still renders
                                         a heading rather than a missing
                                         translation string. --}}
                                    @php $groupKey = 'site.estimator.groups.'.$group; @endphp
                                    {{ Lang::has($groupKey) ? __($groupKey) : $group }}
                                </legend>

                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    @foreach ($items as $item)
                                        <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-xl border border-line p-4 transition hover:border-petrol has-[:checked]:border-petrol has-[:checked]:bg-cream">
                                            <input
                                                type="checkbox"
                                                value="{{ $item->key }}"
                                                x-model="selectedItems"
                                                class="mt-0.5 h-5 w-5 shrink-0 rounded border-line text-orange-ink focus:ring-petrol"
                                            >
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-bold text-ink">{{ $item->label }}</span>
                                                <span class="mt-1 block text-xs font-bold text-orange-ink" dir="ltr">
                                                    {{ number_format((float) $item->price, 0) }} {{ __('site.estimator.currency') }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    {{-- ---------------------------------------------------
                         The running total
                    --------------------------------------------------- --}}
                    <aside class="lg:sticky lg:top-24">
                        <div class="rounded-2xl border border-line bg-white p-6">
                            <h2 class="text-lg font-black text-ink">{{ __('site.estimator.summary') }}</h2>

                            {{-- Nothing ticked yet. --}}
                            <p x-show="! hasSelection" class="mt-4 text-sm text-muted-ink">
                                {{ __('site.estimator.empty_selection') }}
                            </p>

                            <div x-show="hasSelection" x-cloak class="mt-4">
                                <ul class="space-y-2 border-b border-line pb-4">
                                    <template x-for="line in selectedLines" :key="line.id">
                                        <li class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="min-w-0 text-ink" x-text="line.label"></span>
                                            <span class="shrink-0 font-bold text-muted-ink" dir="ltr">
                                                <span x-show="line.price !== null" x-text="formatPrice(line.price)"></span>
                                                <span x-show="line.price === null" class="text-xs">{{ __('site.estimator.on_request') }}</span>
                                            </span>
                                        </li>
                                    </template>
                                </ul>

                                <div class="mt-4">
                                    {{-- "From X" when part of the selection
                                         is priced-on-request: the real
                                         figure can only be higher, and
                                         printing a plain total would quote
                                         less than the visitor will pay. --}}
                                    <p class="text-xs font-bold uppercase tracking-wide text-muted-ink">
                                        <span x-show="! isPartial">{{ __('site.estimator.estimated_total') }}</span>
                                        <span x-show="isPartial" x-cloak>{{ __('site.estimator.estimated_from') }}</span>
                                    </p>
                                    <p class="mt-1 flex items-baseline gap-2" dir="ltr">
                                        <span class="text-3xl font-black text-orange-ink" x-text="formattedTotal"></span>
                                        <span class="text-sm font-bold text-muted-ink">{{ __('site.estimator.currency') }}</span>
                                    </p>

                                    <p x-show="isPartial" x-cloak class="mt-2 text-xs text-muted-ink">
                                        {{ __('site.estimator.partial_note') }}
                                    </p>
                                </div>

                                <button type="button" @click="reset()" class="mt-4 inline-flex min-h-[44px] items-center text-sm font-bold text-petrol underline underline-offset-2 hover:text-orange-ink">
                                    {{ __('site.estimator.reset') }}
                                </button>
                            </div>

                            {{-- Always visible, selection or not. --}}
                            <p class="mt-6 rounded-xl bg-cream p-4 text-xs leading-relaxed text-muted-ink">
                                {{ __('site.estimator.disclaimer') }}
                            </p>
                        </div>

                        <div class="mt-4 lg:hidden">
                            <a href="#estimate-form" class="btn btn-primary w-full">{{ __('site.estimator.request_cta') }}</a>
                        </div>
                    </aside>

                    {{-- ---------------------------------------------------
                         The optional lead step
                    --------------------------------------------------- --}}
                    <div class="lg:col-span-2" id="estimate-form">
                        <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                            <h2 class="text-xl font-black text-ink">{{ __('site.estimator.request_title') }}</h2>
                            <p class="mt-2 text-sm text-muted-ink">{{ __('site.estimator.request_lead') }}</p>

                            <div class="mt-6">
                            <x-public.form-shell :action="lroute('estimator.store')">
                                {{--
                                    The selection travels as hidden inputs
                                    written by Alpine, so one <form> does not
                                    have to wrap the whole picker — the
                                    checkboxes above are outside it and stay
                                    plain controls.

                                    ONLY KEYS ARE SENT. No price and no total
                                    leaves the browser: the server re-reads
                                    both from the catalogue, so a tampered
                                    payload cannot change what the business
                                    sees it quoted.
                                --}}
                                <input type="hidden" name="package" :value="selectedPackage">
                                <template x-for="key in selectedItems" :key="key">
                                    <input type="hidden" name="items[]" :value="key">
                                </template>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <x-public.form-field name="full_name" :label="__('site.estimator.full_name')" required />
                                    <x-public.form-field name="email" type="email" dir="ltr" :label="__('site.estimator.email')" required />
                                    <x-public.form-field name="phone" type="tel" dir="ltr" :label="__('site.estimator.phone_optional')" />
                                </div>

                                <x-public.form-field name="message" type="textarea" :rows="4" :label="__('site.estimator.message')" />

                                <x-public.consent-field />

                                <button type="submit" class="btn btn-primary w-full sm:w-auto">
                                    {{ __('site.estimator.submit') }}
                                </button>
                            </x-public.form-shell>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.public>
