<x-layouts.app :title="$lead->full_name">
    <x-slot:header>
        <x-dashboard.page-header :title="$lead->full_name" :subtitle="__('dashboard.lead_types.'.$lead->type)" :back="route('dashboard.leads.index')" />
    </x-slot:header>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-dashboard.card :title="__('dashboard.leads.enquiry')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'name' => $lead->full_name,
                        'email' => $lead->email,
                        'phone' => $lead->phone,
                        'received' => $lead->created_at->translatedFormat('j F Y H:i'),
                    ] as $key => $value)
                        @if ($value)
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.'.$key) }}</dt>
                                <dd class="mt-1 text-sm font-bold text-ink" @if (in_array($key, ['email', 'phone'], true)) dir="ltr" @endif>{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if ($lead->message)
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.message') }}</dt>
                        {{-- Escaped, always. This is visitor-submitted text. --}}
                        <dd class="mt-1 whitespace-pre-line text-sm text-muted-ink">{{ $lead->message }}</dd>
                    </div>
                @endif
            </x-dashboard.card>

            <x-dashboard.card :title="__('dashboard.leads.consent')">
                <p class="text-sm text-muted-ink">
                    @if ($lead->consent_given)
                        {{ __('dashboard.leads.consent_given', ['date' => $lead->consented_at?->translatedFormat('j F Y H:i') ?? '—']) }}
                    @else
                        {{ __('dashboard.leads.consent_missing') }}
                    @endif
                </p>
            </x-dashboard.card>
        </div>

        <div class="space-y-6">
            <x-dashboard.card :title="__('dashboard.leads.attribution')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'source_platform' => $lead->source_platform,
                        'utm_source' => $lead->utm_source,
                        'utm_medium' => $lead->utm_medium,
                        'utm_campaign' => $lead->utm_campaign,
                        'campaign_id' => $lead->campaign_id,
                        'gclid' => $lead->gclid,
                        'fbclid' => $lead->fbclid,
                        'ttclid' => $lead->ttclid,
                        'landing_page_url' => $lead->landing_page_url,
                        'referrer_url' => $lead->referrer_url,
                    ] as $key => $value)
                        @if ($value)
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.'.$key) }}</dt>
                                <dd class="mt-0.5 break-all text-ink" dir="ltr">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    @if ($lead->campaign)
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.linked_campaign') }}</dt>
                            <dd class="mt-0.5 text-ink">{{ $lead->campaign->name }}</dd>
                        </div>
                    @endif

                    @if (! $lead->source_platform && ! $lead->utm_source)
                        <p class="text-muted-ink">{{ __('dashboard.leads.no_attribution') }}</p>
                    @endif
                </dl>
            </x-dashboard.card>

            @if ($lead->first_touch || $lead->latest_touch)
                <x-dashboard.card :title="__('dashboard.leads.snapshots')" :subtitle="__('dashboard.leads.snapshots_help')">
                    @foreach (['first_touch' => $lead->first_touch, 'latest_touch' => $lead->latest_touch] as $key => $snapshot)
                        @if ($snapshot)
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.'.$key) }}</p>
                                {{-- The snapshot exactly as it was stored,
                                     rendered as escaped text — it is
                                     client-supplied JSON. --}}
                                <pre class="mt-1 overflow-x-auto rounded-lg bg-cream p-3 text-xs text-ink" dir="ltr">{{ json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    @endforeach
                </x-dashboard.card>
            @endif
        </div>
    </div>
</x-layouts.app>
