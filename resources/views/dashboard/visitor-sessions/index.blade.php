@php
    // Shared by the table and the export link, so a filtered export always
    // carries exactly the query string the table was built from.
    $currentFilters = request()->only(['converted', 'source', 'device', 'date_from', 'date_to']);
@endphp

<x-layouts.app :title="__('dashboard.visitor_sessions.title')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.visitor_sessions.title')"
            :subtitle="__('dashboard.visitor_sessions.subtitle')"
        />
    </x-slot:header>

    {{-- An empty table with no explanation reads as a broken feature.
         These two notices are the difference between "nothing happened"
         and "here is why nothing happened". --}}
    @unless ($trackingEnabled)
        <div role="alert" class="mb-6 rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm font-bold text-ink">
            {{ __('dashboard.visitor_sessions.disabled_notice') }}
        </div>
    @endunless

    <p class="mb-6 text-xs leading-relaxed text-muted-ink">
        {{ __('dashboard.visitor_sessions.consent_notice', ['days' => $retentionDays]) }}
    </p>

    {{-- GET, so a filtered view is a shareable URL and the back button
         works. withQueryString() on the paginator keeps the filters
         across pages. --}}
    <form method="GET" action="{{ route('dashboard.visitor-sessions.index') }}" class="mb-6 rounded-2xl border border-line bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-dashboard.field
                name="converted"
                :label="__('dashboard.visitor_sessions.conversion_filter')"
                :value="$filters['converted'] ?? null"
                :options="[
                    '' => __('dashboard.visitor_sessions.all'),
                    'converted' => __('dashboard.visitor_sessions.converted'),
                    'not_converted' => __('dashboard.visitor_sessions.not_converted'),
                ]"
            />

            <x-dashboard.field
                name="source"
                :label="__('dashboard.visitor_sessions.source')"
                :value="$filters['source'] ?? null"
                :options="['' => __('dashboard.visitor_sessions.all')] + $sources->mapWithKeys(fn ($s) => [$s->key => $s->label])->all()"
            />

            <x-dashboard.field
                name="device"
                :label="__('dashboard.visitor_sessions.device')"
                :value="$filters['device'] ?? null"
                :options="['' => __('dashboard.visitor_sessions.all')] + collect($devices)->mapWithKeys(fn ($d) => [$d => __('dashboard.visitor_sessions.device_'.$d)])->all()"
            />

            <x-dashboard.field name="date_from" type="date" dir="ltr" :label="__('dashboard.visitor_sessions.date_from')" :value="$filters['date_from'] ?? null" />
            <x-dashboard.field name="date_to" type="date" dir="ltr" :label="__('dashboard.visitor_sessions.date_to')" :value="$filters['date_to'] ?? null" />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.visitor_sessions.filter') }}</button>
            <a href="{{ route('dashboard.visitor-sessions.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                {{ __('dashboard.visitor_sessions.reset') }}
            </a>

            {{-- Carries the CURRENT filters, so the file always matches
                 the table it was exported from. --}}
            <a
                href="{{ route('dashboard.visitor-sessions.export', $currentFilters) }}"
                class="ms-auto inline-flex min-h-[44px] items-center rounded-lg border border-petrol px-4 text-sm font-bold text-petrol transition hover:bg-cream"
            >
                {{ __('dashboard.visitor_sessions.export') }}
            </a>
        </div>

        <p class="mt-3 text-xs text-muted-ink">
            {{ __('dashboard.visitor_sessions.export_limit_notice', ['limit' => number_format($exportLimit)]) }}
        </p>
    </form>

    @if ($sessions->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.visitor_sessions.empty')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.started_at') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.entry_page') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.pages') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.duration') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.device') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.source') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.visitor_sessions.conversion_filter') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-ink">
                                {{ $session->started_at?->translatedFormat('j M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                {{-- dir="ltr" whatever the interface
                                     language: a path is never Arabic text,
                                     and RTL would render "/en/services"
                                     with the slash at the wrong end. --}}
                                <span dir="ltr" class="break-all text-ink">{{ $session->entry_url }}</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format($session->page_count) }}</td>
                            <td class="px-4 py-3 tabular-nums text-muted-ink" dir="ltr">{{ $session->durationForHumans() }}</td>
                            <td class="px-4 py-3">
                                {{ $session->device_type ? __('dashboard.visitor_sessions.device_'.$session->device_type) : __('dashboard.common.none') }}
                                @if ($session->browser)
                                    <span class="block text-xs text-muted-ink">{{ $session->browser }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span dir="ltr">{{ $session->sourcePlatform() ?? __('dashboard.common.none') }}</span>
                                @if ($session->campaignName())
                                    <span class="block text-xs text-muted-ink" dir="ltr">{{ $session->campaignName() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($session->hasConverted())
                                    <x-dashboard.badge tone="success">{{ __('dashboard.visitor_sessions.converted') }}</x-dashboard.badge>
                                @else
                                    <x-dashboard.badge tone="muted">{{ __('dashboard.visitor_sessions.not_converted') }}</x-dashboard.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('dashboard.visitor-sessions.show', $session) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.visitor_sessions.view') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $sessions->links() }}</div>
    @endif
</x-layouts.app>
