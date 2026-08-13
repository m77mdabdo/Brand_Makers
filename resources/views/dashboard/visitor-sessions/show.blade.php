<x-layouts.app :title="__('dashboard.visitor_sessions.journey')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.visitor_sessions.journey')"
            :subtitle="$session->started_at?->translatedFormat('j M Y H:i')
                .' · '.$session->durationForHumans()
                .' · '.trans_choice('dashboard.visitor_sessions.page_count', $session->page_count, ['count' => number_format($session->page_count)])"
            :back="route('dashboard.visitor-sessions.index')"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.visitor-sessions.export-one', $session) }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-petrol px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                    {{ __('dashboard.visitor_sessions.export_one') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- The timeline is the point of this page, so it takes two thirds
             at xl and the whole width below it. --}}
        <div class="space-y-6 xl:col-span-2">
            @if ($session->isTruncated())
                <div role="alert" class="rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm text-ink">
                    {{ __('dashboard.visitor_sessions.journey_truncated', ['limit' => App\Models\VisitorSession::MAX_EVENTS]) }}
                </div>
            @endif

            <x-dashboard.card :title="__('dashboard.visitor_sessions.journey')">
                @if ($journey === [])
                    <p class="text-sm text-muted-ink">{{ __('dashboard.visitor_sessions.empty') }}</p>
                @else
                    <ol class="relative">
                        @foreach ($journey as $index => $event)
                            @php
                                $isAction = ($event['type'] ?? null) === App\Models\VisitorSession::TYPE_ACTION;
                                $isLast = $index === count($journey) - 1;
                                $timestamp = Illuminate\Support\Carbon::parse($event['ts']);
                                // The action labels the server writes are
                                // a fixed, known set — translated when we
                                // recognise one, printed raw when we do
                                // not, so a label added later still shows
                                // rather than rendering a missing key.
                                $labelKey = 'dashboard.visitor_sessions.labels.'.($event['label'] ?? '');
                            @endphp

                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                {{-- The connecting rail, drawn on every
                                     step but the last so the line ends at
                                     the final dot rather than trailing
                                     into nothing.

                                     start-[11px], not left: it has to flip
                                     with the dashboard's direction, and a
                                     rail on the wrong side in Arabic would
                                     sit across the text. --}}
                                @unless ($isLast)
                                    <span class="absolute bottom-0 top-6 start-[11px] w-px bg-line" aria-hidden="true"></span>
                                @endunless

                                <span @class([
                                    'relative z-10 mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-4 ring-white',
                                    'bg-orange-ink' => $isAction,
                                    'bg-petrol/15' => ! $isAction,
                                ])>
                                    @if ($isAction)
                                        <svg class="h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    @else
                                        <span class="h-2 w-2 rounded-full bg-petrol" aria-hidden="true"></span>
                                    @endif
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span @class([
                                            'text-xs font-extrabold uppercase tracking-wide',
                                            'text-orange-ink' => $isAction,
                                            'text-muted-ink' => ! $isAction,
                                        ])>
                                            {{ $isAction ? __('dashboard.visitor_sessions.action') : __('dashboard.visitor_sessions.pageview') }}
                                        </span>
                                        <span class="text-xs tabular-nums text-muted-ink" dir="ltr">{{ $timestamp->translatedFormat('H:i:s') }}</span>
                                    </div>

                                    @if ($isAction && ! empty($event['label']))
                                        <p class="mt-1 text-sm font-bold text-ink">
                                            {{ Lang::has($labelKey) ? __($labelKey) : $event['label'] }}
                                        </p>
                                    @endif

                                    <p class="mt-1 break-all text-sm text-ink" dir="ltr">{{ $event['url'] ?? __('dashboard.common.none') }}</p>

                                    <p class="mt-1 text-xs text-muted-ink">
                                        {{ __('dashboard.visitor_sessions.time_on_page') }}:
                                        @if (! empty($event['duration_ms']))
                                            <span class="tabular-nums" dir="ltr">{{ number_format($event['duration_ms'] / 1000, 1) }}s</span>
                                        @else
                                            {{ __('dashboard.visitor_sessions.no_duration') }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-dashboard.card>
        </div>

        <div class="space-y-6">
            <x-dashboard.card :title="__('dashboard.visitor_sessions.session_details')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'entry_page' => $session->entry_url,
                        'referrer' => $session->referrer,
                        'device' => $session->device_type ? __('dashboard.visitor_sessions.device_'.$session->device_type) : null,
                        'country' => $session->country,
                    ] as $key => $value)
                        <div class="flex flex-wrap justify-between gap-2">
                            <dt class="text-muted-ink">{{ __('dashboard.visitor_sessions.'.$key) }}</dt>
                            <dd class="min-w-0 break-all text-end font-bold text-ink" dir="auto">{{ $value ?: __('dashboard.common.none') }}</dd>
                        </div>
                    @endforeach

                    @if ($session->browser || $session->platform)
                        <div class="flex flex-wrap justify-between gap-2">
                            <dt class="text-muted-ink">{{ $session->browser ?? __('dashboard.common.none') }}</dt>
                            <dd class="text-end font-bold text-ink">{{ $session->platform ?? __('dashboard.common.none') }}</dd>
                        </div>
                    @endif

                    <div class="flex flex-wrap justify-between gap-2 border-t border-line pt-3">
                        <dt class="text-muted-ink">
                            {{ __('dashboard.visitor_sessions.ip_prefix') }}
                            <span class="block text-xs text-muted-ink/80">{{ __('dashboard.visitor_sessions.ip_prefix_hint') }}</span>
                        </dt>
                        <dd class="text-end font-bold tabular-nums text-ink" dir="ltr">{{ $session->ip_prefix ?? __('dashboard.common.none') }}</dd>
                    </div>
                </dl>
            </x-dashboard.card>

            <x-dashboard.card :title="__('dashboard.visitor_sessions.lead')">
                @if ($session->lead)
                    {{-- The enquiry's TYPE and date, not the visitor's
                         name or contact details. This screen is about the
                         journey; the Leads section is where the person
                         behind it is looked up, and it is the screen with
                         the archive control and the audit trail. --}}
                    <p class="text-sm text-ink">
                        <x-dashboard.badge tone="neutral">{{ __('dashboard.lead_types.'.$session->lead->type) }}</x-dashboard.badge>
                        <span class="ms-2 text-xs text-muted-ink">{{ $session->lead->created_at?->translatedFormat('j M Y H:i') }}</span>
                    </p>
                    <a href="{{ route('dashboard.leads.show', $session->lead) }}" class="inline-flex min-h-[44px] items-center text-sm font-bold text-petrol underline underline-offset-2 hover:text-orange-ink">
                        {{ __('dashboard.visitor_sessions.view_lead') }}
                    </a>
                @else
                    <p class="text-sm text-muted-ink">{{ __('dashboard.visitor_sessions.no_lead') }}</p>
                @endif
            </x-dashboard.card>

            <x-dashboard.card :title="__('dashboard.visitor_sessions.attribution')">
                @php $attribution = $session->attribution ?? []; @endphp

                @if ($attribution === [])
                    <p class="text-sm text-muted-ink">{{ __('dashboard.visitor_sessions.no_attribution') }}</p>
                @else
                    @foreach (['first_touch', 'latest_touch'] as $touch)
                        @if (! empty($attribution[$touch]) && is_array($attribution[$touch]))
                            <div>
                                <p class="text-xs font-extrabold uppercase tracking-wide text-muted-ink">
                                    {{ __('dashboard.visitor_sessions.'.$touch) }}
                                </p>
                                <dl class="mt-2 space-y-1 text-sm">
                                    @foreach ($attribution[$touch] as $key => $value)
                                        @if (is_scalar($value) && filled($value))
                                            <div class="flex flex-wrap justify-between gap-2">
                                                <dt class="text-muted-ink" dir="ltr">{{ $key }}</dt>
                                                <dd class="min-w-0 break-all text-end text-ink" dir="ltr">{{ $value }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    @endforeach
                @endif
            </x-dashboard.card>
        </div>
    </div>
</x-layouts.app>
