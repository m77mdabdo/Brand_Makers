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

            @if ($lead->estimate_snapshot)
                {{--
                    The estimate exactly as the visitor was shown it.

                    Rendered from the SNAPSHOT, never by re-reading the
                    catalogue: prices change and labels get rewritten, and
                    a breakdown that silently updated itself after the next
                    price rise would misrepresent what was quoted. Every
                    value here is read defensively — the column is JSON, so
                    an older or hand-edited shape must degrade to a shorter
                    table rather than a 500.
                --}}
                @php
                    $estimate = $lead->estimate_snapshot;

                    /*
                     * Labels are stored as the full per-locale map so the
                     * breakdown reads correctly for either admin. Resolved
                     * here in the DASHBOARD locale, falling back to Arabic
                     * (the primary), then to whatever single string an
                     * older snapshot happens to hold.
                     */
                    $localised = function ($value) {
                        if (is_string($value)) {
                            return $value;
                        }

                        if (! is_array($value)) {
                            return null;
                        }

                        return $value[app()->getLocale()] ?? $value[config('app.fallback_locale')] ?? reset($value) ?: null;
                    };
                @endphp

                <x-dashboard.card :title="__('dashboard.leads.estimate')" :subtitle="__('dashboard.leads.estimate_help')">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[24rem] text-sm">
                            <tbody class="divide-y divide-line">
                                @if (! empty($estimate['package']) && is_array($estimate['package']))
                                    <tr>
                                        <td class="py-2">
                                            <span class="font-bold text-ink">{{ $localised($estimate['package']['name'] ?? null) ?? $estimate['package']['slug'] ?? '—' }}</span>
                                            <span class="block text-xs text-muted-ink">{{ __('dashboard.leads.estimate_base_package') }}</span>
                                        </td>
                                        <td class="py-2 text-end tabular-nums" dir="ltr">
                                            @if (($estimate['package']['price'] ?? null) !== null)
                                                {{ number_format((float) $estimate['package']['price'], 2) }}
                                            @else
                                                <span class="text-xs font-bold text-muted-ink">{{ __('dashboard.leads.estimate_on_request') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif

                                @foreach ($estimate['items'] ?? [] as $line)
                                    @if (is_array($line))
                                        <tr>
                                            <td class="py-2">
                                                <span class="text-ink">{{ $localised($line['label'] ?? null) ?? $line['key'] ?? '—' }}</span>
                                            </td>
                                            <td class="py-2 text-end tabular-nums" dir="ltr">
                                                {{ number_format((float) ($line['price'] ?? 0), 2) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-line">
                                <tr>
                                    <th class="py-3 text-start font-extrabold text-ink">
                                        {{ __('dashboard.leads.estimate_total') }}
                                    </th>
                                    <td class="py-3 text-end" dir="ltr">
                                        @if ($lead->estimate_total !== null)
                                            <span class="text-lg font-black tabular-nums text-orange-ink">
                                                {{ number_format((float) $lead->estimate_total, 2) }}
                                            </span>
                                            <span class="text-xs font-bold text-muted-ink">{{ $estimate['currency'] ?? 'SAR' }}</span>
                                        @else
                                            {{-- null, not 0.00: every line was
                                                 priced on request, and a zero
                                                 would be a different claim. --}}
                                            <span class="text-xs font-bold text-muted-ink">{{ __('dashboard.leads.estimate_no_total') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if (! empty($estimate['is_partial']))
                        <p class="rounded-lg bg-cream p-3 text-xs text-muted-ink">
                            {{ __('dashboard.leads.estimate_partial') }}
                        </p>
                    @endif
                </x-dashboard.card>
            @endif

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
