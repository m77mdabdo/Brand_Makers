<x-layouts.app :title="__('dashboard.resources.estimator_items')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.estimator_items')"
            :subtitle="__('dashboard.estimator_items.intro')"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.estimator-items.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($activeCount === 0)
        {{-- The catalogue ships priced at 0.00 and inactive, so this is
             the state of a fresh install. Saying so here is the difference
             between "the estimator is broken" and "nobody has priced it
             yet" — the public page shows its pricing-in-preparation
             notice for exactly as long as this is true. --}}
        <div role="status" class="mb-6 rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm text-ink">
            {{ __('dashboard.estimator_items.none_active') }}
        </div>
    @endif

    @if ($items->isEmpty())
        <x-dashboard.empty-state
            :message="__('dashboard.estimator_items.empty')"
            :action="route('dashboard.estimator-items.create')"
            :action-label="__('dashboard.actions.create')"
        />
    @else
        {{-- overflow-x-auto on the wrapper, never on the page: a wide
             table scrolls inside its own box rather than making the whole
             dashboard scroll sideways on a phone. --}}
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.estimator_items.label_field') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.estimator_items.group') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.estimator_items.price') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $item->label }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $item->key }}</span>
                            </td>
                            <td class="px-4 py-3" dir="ltr">
                                @php $groupKey = 'site.estimator.groups.'.$item->group; @endphp
                                {{ Lang::has($groupKey) ? __($groupKey) : $item->group }}
                            </td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">
                                @if ((float) $item->price > 0)
                                    {{ number_format((float) $item->price, 2) }} {{ $item->currency }}
                                @else
                                    {{-- price is NOT NULL, so 0.00 is how
                                         "not priced yet" is stored. Printing
                                         a bare 0 would read as free. --}}
                                    <span class="text-xs font-bold text-muted-ink">{{ __('dashboard.estimator_items.unpriced') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ $item->sort_order }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="$item->is_active ? 'success' : 'muted'">
                                    {{ $item->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.estimator-items.edit', $item) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.estimator-items.destroy', $item)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $items->links() }}</div>
    @endif
</x-layouts.app>
