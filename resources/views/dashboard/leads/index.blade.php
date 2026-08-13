<x-layouts.app :title="__('dashboard.resources.leads')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.leads')" :subtitle="__('dashboard.leads.intro')" />
    </x-slot:header>

    {{-- GET, so a filtered view is a shareable URL and the browser back
         button works. withQueryString() on the paginator keeps the
         filters across pages. --}}
    <form method="GET" action="{{ route('dashboard.leads.index') }}" class="mb-6 rounded-2xl border border-line bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-dashboard.field
                name="type"
                :label="__('dashboard.fields.type')"
                :value="$filters['type'] ?? null"
                :options="['' => __('dashboard.common.all')] + collect(App\Models\Lead::TYPES)->mapWithKeys(fn ($t) => [$t => __('dashboard.lead_types.'.$t)])->all()"
            />

            <x-dashboard.field
                name="source_platform"
                :label="__('dashboard.fields.source_platform')"
                :value="$filters['source_platform'] ?? null"
                :options="['' => __('dashboard.common.all')] + $sourcePlatforms->mapWithKeys(fn ($s) => [$s => $s])->all()"
            />

            <x-dashboard.field
                name="requested_service_id"
                :label="__('dashboard.resources.services')"
                :value="$filters['requested_service_id'] ?? null"
                :options="['' => __('dashboard.common.all')] + $services->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()"
            />

            <x-dashboard.field
                name="package_id"
                :label="__('dashboard.resources.packages')"
                :value="$filters['package_id'] ?? null"
                :options="['' => __('dashboard.common.all')] + $packages->mapWithKeys(fn ($p) => [$p->id => $p->name])->all()"
            />

            <x-dashboard.field name="date_from" type="date" dir="ltr" :label="__('dashboard.fields.date_from')" :value="$filters['date_from'] ?? null" />
            <x-dashboard.field name="date_to" type="date" dir="ltr" :label="__('dashboard.fields.date_to')" :value="$filters['date_to'] ?? null" />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.actions.filter') }}</button>
            <a href="{{ route('dashboard.leads.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                {{ __('dashboard.actions.reset') }}
            </a>
        </div>
    </form>

    @if ($leads->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.leads.empty')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.name') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.type') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.source_platform') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.interest') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.received') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($leads as $lead)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $lead->full_name }}</span>
                                @if ($lead->email)
                                    <span class="block text-xs text-muted-ink" dir="ltr">{{ $lead->email }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge tone="neutral">{{ __('dashboard.lead_types.'.$lead->type) }}</x-dashboard.badge>
                                @if ($lead->type === App\Models\Lead::TYPE_CONTACT && ! $lead->is_handled)
                                    <x-dashboard.badge tone="warning">{{ __('dashboard.status.pending') }}</x-dashboard.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $lead->source_platform ?? __('dashboard.common.none') }}
                                @if ($lead->campaign)
                                    <span class="block text-xs text-muted-ink">{{ $lead->campaign->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{-- Whichever catalogue the enquiry came
                                     from. Both resolve withTrashed(), so an
                                     archived service or retired package still
                                     names itself here. --}}
                                {{ $lead->package?->name ?? $lead->requestedService?->name ?? __('dashboard.common.none') }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-ink">{{ $lead->created_at->translatedFormat('j M Y H:i') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.leads.show', $lead) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.view') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.leads.destroy', $lead)" :label="__('dashboard.actions.archive')" :confirm="__('dashboard.leads.confirm_archive')" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $leads->links() }}</div>
    @endif
</x-layouts.app>
