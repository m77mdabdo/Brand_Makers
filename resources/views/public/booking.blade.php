@php
    /**
     * Availability, handed to the picker whole.
     *
     * Dates are already filtered server-side: closed weekdays, blackout
     * dates, days inside the notice period and slots at capacity are
     * absent, so the client never has to know any of those rules — see
     * BookingSlotService.
     *
     * `label` is formatted here rather than in JavaScript because the
     * month and weekday names have to be the CURRENT LOCALE'S, and
     * translatedFormat() already knows them.
     */
    $availability = [
        'days' => $days->map(fn (array $day) => [
            'date' => $day['date']->toDateString(),
            'label' => $day['date']->translatedFormat('l j F'),
            'slots' => collect($day['slots'])->map(fn (array $slot) => [
                'time' => $slot['time'],
                'remaining' => $slot['remaining'],
            ])->values()->all(),
        ])->values()->all(),
        'selectedDate' => old('date', ''),
        'selectedTime' => old('time', ''),
    ];
@endphp

<x-layouts.public :seo-title="__('site.booking.title')" :seo-description="__('site.booking.lead')">
    <x-public.page-hero
        :eyebrow="__('site.booking.eyebrow')"
        :title="__('site.booking.title')"
        :lead="__('site.booking.lead')"
    />

    <x-public.breadcrumbs :items="[__('site.nav.home') => lroute('home'), __('site.booking.label') => null]" />

    <section class="section" id="booking">
        <div class="wrap max-w-4xl">
            <div id="booking-confirmed" tabindex="-1">
                {{-- The confirmation. Rendered only when a booking has just
                     been taken, and the reference comes from the FLASH
                     rather than the URL — see BookingController::store()
                     for why. --}}
                @if (session('booking_reference'))
                    <div role="status" class="mb-8 rounded-2xl border border-petrol/20 bg-white p-6 sm:p-8">
                        <h2 class="text-xl font-black text-ink">{{ __('site.booking.confirmed_title') }}</h2>
                        <p class="mt-2 text-sm text-muted-ink">{{ __('site.booking.confirmed_lead') }}</p>

                        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-cream p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('site.booking.reference') }}</dt>
                                <dd class="mt-1 text-lg font-black tracking-wide text-orange-ink" dir="ltr">{{ session('booking_reference') }}</dd>
                            </div>
                            <div class="rounded-xl bg-cream p-4">
                                <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('site.booking.appointment') }}</dt>
                                <dd class="mt-1 text-sm font-bold text-ink">{{ session('booking_when') }}</dd>
                            </div>
                        </dl>

                        {{-- Says the studio will be in touch. Deliberately
                             does NOT say an email is on its way: no
                             transport is configured yet, and this sentence
                             has to stay true — see BookingNotifier. --}}
                        <p class="mt-5 text-sm text-muted-ink">{{ __('site.booking.confirmed_next', ['timezone' => __('site.booking.timezone_label')]) }}</p>
                    </div>
                @endif
            </div>

            <x-public.flash />

            @if (! $isConfigured || $days->isEmpty())
                {{--
                    Two different reasons to show nothing, and both are
                    honest about which it is: no availability has been
                    configured at all, or it has but the window is
                    currently empty (fully booked, or every day blacked
                    out). Neither invents a slot.
                --}}
                <div class="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-8 text-center">
                    <h2 class="text-xl font-black text-ink">
                        {{ $isConfigured ? __('site.booking.none_available') : __('site.booking.not_open') }}
                    </h2>
                    <p class="mt-3 text-sm text-muted-ink">
                        {{ $isConfigured ? __('site.booking.none_available_hint') : __('site.booking.not_open_hint') }}
                    </p>
                    <a href="{{ lroute('contact') }}" class="btn btn-primary mt-6">{{ __('site.booking.contact_cta') }}</a>
                </div>
            @else
                <div
                    x-data="bookingPicker(window.bookingConfig($el))"
                    data-booking-availability="{{ json_encode($availability, JSON_UNESCAPED_UNICODE) }}"
                    id="booking-form"
                >
                    <x-public.form-shell :action="lroute('booking.store')">
                        {{-- No hidden inputs: the day and time controls below
                             ARE the form fields. Whatever they submit, the
                             server re-derives the whole slot list and looks
                             the pair up — a crafted pair cannot become an
                             appointment. --}}

                        {{-- ---------------------------------------------
                             1. Topic
                        --------------------------------------------- --}}
                        <fieldset class="rounded-2xl border border-line bg-white p-6">
                            <legend class="px-2 text-lg font-black text-ink">{{ __('site.booking.step_topic') }}</legend>
                            <p class="mt-1 text-sm text-muted-ink">{{ __('site.booking.step_topic_help') }}</p>

                            <div class="mt-4">
                                <label for="service_id" class="sr-only">{{ __('site.booking.topic') }}</label>
                                <select
                                    id="service_id"
                                    name="service_id"
                                    class="block min-h-[44px] w-full rounded-xl border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                >
                                    {{-- "Not sure yet" is a real answer and
                                         the default: forcing a choice on a
                                         consultation form costs bookings. --}}
                                    <option value="">{{ __('site.booking.topic_unsure') }}</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                            </div>
                        </fieldset>

                        {{-- ---------------------------------------------
                             2. Date
                        --------------------------------------------- --}}
                        <fieldset class="rounded-2xl border border-line bg-white p-6">
                            <legend class="px-2 text-lg font-black text-ink">{{ __('site.booking.step_date') }}</legend>
                            <p class="mt-1 text-sm text-muted-ink">
                                {{ __('site.booking.timezone_note', ['timezone' => __('site.booking.timezone_label')]) }}
                            </p>

                            {{--
                                NATIVE RADIOS, not buttons carrying
                                role="radio".

                                The previous markup announced a radiogroup
                                and did not implement one: the WAI-ARIA
                                pattern requires arrow-key navigation with a
                                roving tabindex, so a screen-reader user
                                told "radio group, 1 of 21" pressed the
                                arrow keys and nothing happened.

                                Hand-rolling that is the wrong fix. A real
                                <input type="radio"> already gives arrow-key
                                navigation, aria-checked, a single tab stop
                                for the group, form submission and — the
                                part easiest to get wrong by hand — arrow
                                directions that flip correctly under RTL,
                                because the browser knows the writing
                                direction. The estimator uses the same
                                pattern for the same reason.

                                The input is sr-only rather than hidden, so
                                it stays focusable, and `peer` drives the
                                label's selected and focus styling.
                            --}}
                            <div class="mt-4 flex flex-wrap gap-2">
                                <template x-for="day in days" :key="day.date">
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="date"
                                            class="peer sr-only"
                                            :value="day.date"
                                            x-model="selectedDate"
                                            @change="onDateChange()"
                                        >
                                        <span class="inline-flex min-h-[44px] items-center rounded-xl border border-line bg-white px-4 text-sm font-bold text-ink transition hover:border-petrol peer-checked:border-petrol peer-checked:bg-petrol peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-petrol peer-focus-visible:ring-offset-2">
                                            <span x-text="day.label"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>

                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </fieldset>

                        {{-- ---------------------------------------------
                             3. Time
                        --------------------------------------------- --}}
                        {{-- Always rendered, never x-show'd away.

                             Hiding the whole step until a date is chosen
                             left the visible numbering reading 1, 2, 4 —
                             which looks like a missing step rather than a
                             sequence. It stays in place and says what to
                             do instead. --}}
                        <fieldset class="rounded-2xl border border-line bg-white p-6">
                            <legend class="px-2 text-lg font-black text-ink">{{ __('site.booking.step_time') }}</legend>

                            <p x-show="! selectedDate" class="mt-1 text-sm text-muted-ink">
                                {{ __('site.booking.pick_a_day_first') }}
                            </p>

                            <div x-show="selectedDate" x-cloak class="mt-4 flex flex-wrap gap-2">
                                <template x-for="slot in slots" :key="slot.time">
                                    <label class="cursor-pointer" dir="ltr">
                                        <input
                                            type="radio"
                                            name="time"
                                            class="peer sr-only"
                                            :value="slot.time"
                                            x-model="selectedTime"
                                        >
                                        <span class="inline-flex min-h-[44px] items-center rounded-xl border border-line bg-white px-4 text-sm font-bold tabular-nums text-ink transition hover:border-orange-ink peer-checked:border-orange-ink peer-checked:bg-orange-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-petrol peer-focus-visible:ring-offset-2">
                                            <span x-text="slot.time"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>

                            <x-input-error :messages="$errors->get('time')" class="mt-2" />
                        </fieldset>

                        {{-- ---------------------------------------------
                             4. Details
                        --------------------------------------------- --}}
                        <fieldset class="rounded-2xl border border-line bg-white p-6">
                            <legend class="px-2 text-lg font-black text-ink">{{ __('site.booking.step_details') }}</legend>

                            <p x-show="hasSelection" x-cloak class="mt-2 rounded-xl bg-cream px-4 py-3 text-sm font-bold text-petrol">
                                {{ __('site.booking.selected') }}: <span x-text="summary"></span>
                            </p>

                            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                                <x-public.form-field name="name" :label="__('site.booking.full_name')" required />
                                <x-public.form-field name="email" type="email" dir="ltr" :label="__('site.booking.email')" required />
                                <x-public.form-field name="phone" type="tel" dir="ltr" :label="__('site.booking.phone_optional')" />
                            </div>

                            <div class="mt-5">
                                <x-public.form-field name="notes" type="textarea" :rows="4" :label="__('site.booking.notes')" />
                            </div>

                            <div class="mt-5">
                                <x-public.consent-field />
                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary mt-6 w-full sm:w-auto"
                                {{-- Disabled until a slot is chosen, so the
                                     commonest mistake is prevented rather
                                     than reported. The server still checks:
                                     this attribute is a courtesy, not the
                                     rule. --}}
                                :disabled="! hasSelection"
                                :class="! hasSelection && 'cursor-not-allowed opacity-50'"
                            >
                                {{ __('site.booking.submit') }}
                            </button>
                        </fieldset>
                    </x-public.form-shell>

                    {{-- Without JavaScript the buttons above never render.
                         This lists the same availability as plain text so a
                         visitor can still see what is on offer and call
                         instead — the form itself needs the picker. --}}
                    <noscript>
                        <div class="mt-6 rounded-2xl border border-line bg-white p-6">
                            <h2 class="text-lg font-black text-ink">{{ __('site.booking.noscript_title') }}</h2>
                            <p class="mt-2 text-sm text-muted-ink">{{ __('site.booking.noscript_hint') }}</p>
                            <ul class="mt-4 space-y-2 text-sm text-ink">
                                @foreach ($days as $day)
                                    <li>
                                        <span class="font-bold">{{ $day['date']->translatedFormat('l j F') }}</span>
                                        <span dir="ltr" class="text-muted-ink">{{ collect($day['slots'])->pluck('time')->implode('، ') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </noscript>
                </div>
            @endif
        </div>
    </section>
</x-layouts.public>
