<x-layouts.app :title="__('dashboard.resources.campaigns')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.campaigns')" :subtitle="__('dashboard.campaigns.intro')">
            <x-slot:actions>
                <a href="{{ route('dashboard.campaigns.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($campaigns->isEmpty())
        <x-dashboard.empty-state
            :message="__('dashboard.campaigns.empty')"
            :action="route('dashboard.campaigns.create')"
            :action-label="__('dashboard.actions.create')"
        />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.campaigns.name') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.campaigns.platform') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.campaigns.external_id') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.campaigns.linked_leads') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.campaigns.spend_heading') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td class="px-4 py-3 font-bold text-ink">{{ $campaign->name }}</td>
                            <td class="px-4 py-3 text-muted-ink">
                                {{ $campaign->platform ? __('dashboard.campaigns.platforms.'.$campaign->platform) : __('dashboard.common.none') }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-ink" dir="ltr">
                                {{ $campaign->external_campaign_id ?: __('dashboard.common.none') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($campaign->leads_count > 0)
                                    <a href="{{ route('dashboard.leads.index') }}" class="font-bold text-petrol tabular-nums underline underline-offset-2">
                                        {{ $campaign->leads_count }}
                                    </a>
                                @else
                                    {{-- Zero with an external id set is the
                                         signal worth noticing: the id does
                                         not match what the ad platform is
                                         actually sending. --}}
                                    <span class="text-xs text-muted-ink">
                                        {{ $campaign->external_campaign_id ? __('dashboard.campaigns.no_leads_yet') : __('dashboard.campaigns.no_external_id') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-muted-ink" dir="ltr">
                                @if ($campaign->spend !== null || $campaign->budget !== null)
                                    {{ $campaign->spend !== null ? number_format((float) $campaign->spend, 0) : '—' }}
                                    / {{ $campaign->budget !== null ? number_format((float) $campaign->budget, 0) : '—' }}
                                    <span class="text-xs">{{ $campaign->currency }}</span>
                                @else
                                    <span class="text-xs">{{ __('dashboard.common.none') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$campaign->is_active ? 'success' : 'muted'">
                                    {{ $campaign->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.campaigns.edit', $campaign) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button
                                        :action="route('dashboard.campaigns.destroy', $campaign)"
                                        :confirm="__('dashboard.campaigns.confirm_delete')"
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $campaigns->links() }}</div>
    @endif
</x-layouts.app>
