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
        // Copy is rendered server-side and handed over, so the client
        // formats no strings of its own and nothing needs translating in
        // JavaScript.
        'labels' => [
            'none' => __('site.estimator.none_selected'),
            'selected' => __('site.estimator.selected_count'),
        ],
        /*
         * Two maps, not one. Services are keyed by slug and items by key,
         * from different tables — merging them would let a collision go
         * unnoticed and charge the wrong line.
         */
        'items' => $groups->where('source', '!==', \App\Models\EstimatorGroup::SOURCE_SERVICES)
            ->pluck('items')->flatten()->mapWithKeys(fn ($item) => [
                // null, not 0 — an on-request line contributes no number
                // and the client must not add one.
                $item->key => ['label' => $item->label, 'price' => $item->isPriceOnRequest() ? null : (string) $item->price],
            ])->all(),
        'services' => $groups->where('source', \App\Models\EstimatorGroup::SOURCE_SERVICES)
            ->pluck('items')->flatten()->mapWithKeys(fn ($service) => [
                $service->slug => ['label' => $service->name, 'price' => $service->isPriceOnRequest() ? null : (string) $service->price],
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
                        {{-- ---------------------------------------
                             نوع التأسيس — the base tier.

                             A <select>, not a radio grid: it is one
                             selector among several now, and rendering it
                             differently from the others made the form read
                             as two unrelated screens stacked. It is still
                             fed by PACKAGES rather than estimator items,
                             because the three tiers deliberately do not
                             exist as items — see EstimatorItemSeeder.
                        --------------------------------------- --}}
                        @if ($packages->isNotEmpty())
                            <div class="rounded-2xl border border-line bg-white p-6">
                                <label for="estimator-package" class="block text-lg font-black text-ink">
                                    {{ __('site.estimator.step_package') }}
                                </label>
                                <p class="mt-1 text-sm text-muted-ink">{{ __('site.estimator.step_package_help') }}</p>

                                <select
                                    id="estimator-package"
                                    x-model="selectedPackage"
                                    class="mt-4 block min-h-[44px] w-full rounded-xl border-line bg-white text-sm font-bold text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                >
                                    {{-- "No base" first and selected by
                                         default: the estimator must be
                                         usable by someone who only wants a
                                         few add-ons, and defaulting to a
                                         tier would put a number on the
                                         screen the visitor never chose. --}}
                                    <option value="">{{ __('site.estimator.no_package') }}</option>

                                    @foreach ($packages as $package)
                                        <option value="{{ $package->slug }}">
                                            {{ $package->name }}
                                            @if ($package->isPriceOnRequest())
                                                — {{ __('site.estimator.on_request') }}
                                            @else
                                                — {{ number_format((float) $package->price, 0) }} {{ __('site.estimator.currency') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @foreach ($groups as $group)
                            @php $groupId = 'estimator-group-'.\Illuminate\Support\Str::slug($group['key']); @endphp

                            @if ($group['multiple'])
                                {{-- ---------------------------------------
                                     A multi-select, built as a disclosure
                                     rather than <select multiple>.

                                     Native multi-selects are close to
                                     unusable on touch — they need a
                                     modifier key to add a second choice,
                                     and their rows cannot be given a 44px
                                     target. This keeps the dropdown shape
                                     the screen asks for while every option
                                     stays a full-height checkbox row.
                                --------------------------------------- --}}
                                <div
                                    class="rounded-2xl border border-line bg-white p-6"
                                    x-data="{ open: false }"
                                    @keydown.escape.stop="open = false"
                                    @click.outside="open = false"
                                >
                                    <p class="text-lg font-black text-ink" id="{{ $groupId }}-label">{{ $group['label'] }}</p>

                                    <button
                                        type="button"
                                        @click="open = ! open"
                                        :aria-expanded="open.toString()"
                                        aria-controls="{{ $groupId }}-panel"
                                        aria-labelledby="{{ $groupId }}-label"
                                        class="mt-4 flex min-h-[44px] w-full items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 text-start text-sm font-bold text-ink transition hover:border-petrol focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                                    >
                                        <span x-text="groupSummary(@js(collect($group['items'])->pluck($group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES ? 'slug' : 'key')->all()), @js($group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES))"></span>
                                        <svg class="h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M6 9l6 6 6-6" stroke-linecap="round" />
                                        </svg>
                                    </button>

                                    <div id="{{ $groupId }}-panel" x-show="open" x-cloak x-transition.opacity class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @foreach ($group['items'] as $item)
                                            @php
                                                $isService = $group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES;
                                                $optionValue = $isService ? $item->slug : $item->key;
                                                $optionLabel = $isService ? $item->name : $item->label;
                                            @endphp
                                            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-xl border border-line p-3 transition hover:border-petrol has-[:checked]:border-petrol has-[:checked]:bg-cream">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $optionValue }}"
                                                    x-model="{{ $isService ? 'selectedServices' : 'selectedItems' }}"
                                                    class="mt-0.5 h-5 w-5 shrink-0 rounded border-line text-orange-ink focus:ring-petrol"
                                                >
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-bold text-ink">{{ $optionLabel }}</span>
                                                    <span class="mt-1 block text-xs font-bold {{ $item->isPriceOnRequest() ? 'text-muted-ink' : 'text-orange-ink' }}" @if (! $item->isPriceOnRequest()) dir="ltr" @endif>
                                                        @if ($item->isPriceOnRequest())
                                                            {{ __('site.estimator.on_request') }}
                                                        @else
                                                            {{ number_format((float) $item->price, 0) }} {{ __('site.estimator.currency') }}
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                {{-- Single answer: a plain <select>. --}}
                                <div class="rounded-2xl border border-line bg-white p-6">
                                    <label for="{{ $groupId }}" class="block text-lg font-black text-ink">{{ $group['label'] }}</label>

                                    <select
                                        id="{{ $groupId }}"
                                        @change="chooseOne(@js(collect($group['items'])->pluck($group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES ? 'slug' : 'key')->all()), $event.target.value, @js($group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES))"
                                        class="mt-4 block min-h-[44px] w-full rounded-xl border-line bg-white text-sm font-bold text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                    >
                                        <option value="">{{ __('site.estimator.none_selected') }}</option>
                                        @foreach ($group['items'] as $item)
                                            @php $isService = $group['source'] === \App\Models\EstimatorGroup::SOURCE_SERVICES; @endphp
                                            <option value="{{ $isService ? $item->slug : $item->key }}">
                                                {{ $isService ? $item->name : $item->label }}
                                                @if ($item->isPriceOnRequest())
                                                    — {{ __('site.estimator.on_request') }}
                                                @else
                                                    — {{ number_format((float) $item->price, 0) }} {{ __('site.estimator.currency') }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
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
                                <template x-for="slug in selectedServices" :key="slug">
                                    <input type="hidden" name="services[]" :value="slug">
                                </template>
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
