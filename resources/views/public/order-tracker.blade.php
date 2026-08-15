<x-layouts.public :seo-title="__('site.tracker.title')" :seo-description="__('site.tracker.lead')">
    <x-public.page-hero
        :eyebrow="__('site.tracker.eyebrow')"
        :title="__('site.tracker.title')"
        :lead="__('site.tracker.lead')"
    />

    <x-public.breadcrumbs :items="[__('site.nav.home') => lroute('home'), __('site.tracker.label') => null]" />

    <section class="section">
        <div class="wrap max-w-3xl">
            <x-public.flash />

            @if ($order)
                {{-- ---------------------------------------------------------
                     The order. Exactly one, held in the session — there is no
                     identifier in the URL to change.
                --------------------------------------------------------- --}}
                <div id="order-status" tabindex="-1" class="space-y-6">
                    <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('site.tracker.reference') }}</p>
                                <p class="mt-1 text-2xl font-black tracking-wide text-ink" dir="ltr">{{ $order->reference }}</p>
                            </div>

                            <span @class([
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold',
                                'bg-petrol/10 text-petrol' => ! $timeline['cancelled'] && ! $order->isComplete(),
                                'bg-petrol text-white' => $order->isComplete(),
                                'bg-cream-2 text-muted-ink' => $timeline['cancelled'],
                            ])>
                                {{ __('site.tracker.statuses.'.$order->status) }}
                            </span>
                        </div>

                        @if ($order->package)
                            <p class="mt-4 text-sm text-muted-ink">
                                {{ __('site.tracker.package') }}:
                                <span class="font-bold text-ink">{{ $order->package->name }}</span>
                            </p>
                        @endif

                        {{-- Progress. The <progress> element carries the value
                             for assistive tech; the bar beside it is the
                             visual, marked aria-hidden so it is not announced
                             twice. --}}
                        <div class="mt-6">
                            <div class="flex items-baseline justify-between gap-3">
                                <label for="order-progress" class="text-xs font-bold uppercase tracking-wide text-muted-ink">
                                    {{ __('site.tracker.progress') }}
                                </label>
                                <span class="text-sm font-black tabular-nums text-orange-ink" dir="ltr">{{ $order->progress }}%</span>
                            </div>

                            <progress id="order-progress" class="sr-only" max="100" value="{{ $order->progress }}">{{ $order->progress }}%</progress>

                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-cream-2" aria-hidden="true">
                                {{-- The width is the one thing here that
                                     genuinely varies per record, so it is an
                                     inline style rather than one of a hundred
                                     generated utility classes. --}}
                                <div class="h-full rounded-full bg-orange-ink transition-all" style="width: {{ $order->progress }}%"></div>
                            </div>
                        </div>
                    </div>

                    {{-- ---------------------------------------------------
                         The stages
                    --------------------------------------------------- --}}
                    <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                        <h2 class="text-lg font-black text-ink">{{ __('site.tracker.stages_heading') }}</h2>

                        @if ($timeline['cancelled'])
                            <p class="mt-2 text-sm font-bold text-muted-ink">{{ __('site.tracker.cancelled_note') }}</p>
                        @endif

                        {{-- An ordered list, so a screen reader announces
                             "1 of 4" and the sequence is conveyed by the
                             markup rather than only by the drawn line. --}}
                        <ol class="mt-5 space-y-4">
                            @foreach ($timeline['stages'] as $stage)
                                <li class="flex items-start gap-4">
                                    <span @class([
                                        'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-black',
                                        'bg-petrol text-white' => $stage['state'] === 'done',
                                        'bg-orange-ink text-white' => $stage['state'] === 'current',
                                        'bg-cream-2 text-muted-ink' => $stage['state'] === 'upcoming',
                                    ]) aria-hidden="true">
                                        @if ($stage['state'] === 'done')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        @else
                                            {{ $stage['position'] + 1 }}
                                        @endif
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span @class([
                                            'block font-bold',
                                            'text-ink' => $stage['state'] !== 'upcoming',
                                            'text-muted-ink' => $stage['state'] === 'upcoming',
                                        ])>
                                            {{ __('site.tracker.stages.'.$stage['status']) }}
                                        </span>

                                        {{-- The state in words, not only in
                                             colour: a screen-reader user and
                                             anyone who cannot distinguish the
                                             petrol from the orange both need
                                             this. --}}
                                        <span class="block text-xs text-muted-ink">
                                            {{ __('site.tracker.stage_states.'.$stage['state']) }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- ---------------------------------------------------
                         What the studio has written
                    --------------------------------------------------- --}}
                    @if ($timeline['events'] !== [])
                        <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                            <h2 class="text-lg font-black text-ink">{{ __('site.tracker.updates_heading') }}</h2>

                            <ol class="mt-5 space-y-5">
                                @foreach ($timeline['events'] as $event)
                                    <li class="border-s-2 border-line ps-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-muted-ink">
                                            <time datetime="{{ $event->occurred_at->toIso8601String() }}">
                                                {{ $event->occurred_at->translatedFormat('j F Y') }}
                                            </time>
                                            · {{ __('site.tracker.statuses.'.$event->status) }}
                                        </p>

                                        @if ($event->note)
                                            {{-- Escaped, always. A note is
                                                 staff-written but it is
                                                 rendered to a client, and it
                                                 is stored as plain text —
                                                 sanitised on write, escaped
                                                 on render. --}}
                                            <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $event->note }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    @if ($order->notes)
                        <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                            <h2 class="text-lg font-black text-ink">{{ __('site.tracker.latest_note') }}</h2>
                            <p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $order->notes }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ lroute('orders.track.forget') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-[44px] items-center rounded-xl border border-line px-5 text-sm font-bold text-petrol transition hover:bg-cream">
                            {{ __('site.tracker.check_another') }}
                        </button>
                    </form>
                </div>
            @else
                {{-- ---------------------------------------------------------
                     The lookup form
                --------------------------------------------------------- --}}
                <div id="track-form" tabindex="-1" class="rounded-2xl border border-line bg-white p-6 sm:p-8">
                    <h2 class="text-xl font-black text-ink">{{ __('site.tracker.form_heading') }}</h2>
                    <p class="mt-2 text-sm text-muted-ink">{{ __('site.tracker.form_hint') }}</p>

                    <x-public.form-shell :action="lroute('orders.track.lookup')">
                        <div class="mt-2 grid gap-5 sm:grid-cols-2">
                            <x-public.form-field
                                name="reference"
                                dir="ltr"
                                :label="__('site.tracker.reference')"
                                required
                            />
                            {{-- type="password" so the code is not left on
                                 screen over a client's shoulder, and
                                 autocomplete off so no browser stores a
                                 credential it was never asked to keep. --}}
                            <div>
                                <label for="tracking_code" class="block text-sm font-bold text-ink">
                                    {{ __('site.tracker.code') }}
                                    <span class="text-orange-ink" aria-hidden="true">*</span>
                                    <span class="sr-only">({{ __('site.forms.required') }})</span>
                                </label>
                                <input
                                    id="tracking_code"
                                    name="tracking_code"
                                    type="password"
                                    dir="ltr"
                                    required
                                    autocomplete="off"
                                    spellcheck="false"
                                    @error('tracking_code') aria-invalid="true" aria-describedby="tracking_code-error" @enderror
                                    class="mt-1 block min-h-[44px] w-full rounded-xl border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                >
                                @error('tracking_code')
                                    <p id="tracking_code-error" class="mt-1 text-sm font-bold text-orange-ink">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full sm:w-auto">
                            {{ __('site.tracker.submit') }}
                        </button>
                    </x-public.form-shell>

                    <p class="mt-6 border-t border-line pt-5 text-xs leading-relaxed text-muted-ink">
                        {{ __('site.tracker.lost_code') }}
                        <a href="{{ lroute('contact') }}" class="font-bold text-orange-ink underline underline-offset-2 hover:text-petrol">
                            {{ __('site.tracker.contact_us') }}
                        </a>
                    </p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.public>
