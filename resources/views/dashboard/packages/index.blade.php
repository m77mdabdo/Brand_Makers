<x-layouts.app :title="__('dashboard.resources.packages')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.packages')" :subtitle="__('dashboard.packages.intro')">
            <x-slot:actions>
                <a href="{{ route('dashboard.packages.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    @if ($unpricedCount > 0)
        {{-- The state a fresh install ships in: every package priced
             "on request". Saying so here is the difference between "the
             pricing page is broken" and "nobody has priced it yet". --}}
        <div role="status" class="mb-6 rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm text-ink">
            {{ trans_choice('dashboard.packages.unpriced_notice', $unpricedCount, ['count' => $unpricedCount]) }}
        </div>
    @endif

    @if ($packages->isEmpty())
        <x-dashboard.empty-state
            :message="__('dashboard.packages.empty')"
            :action="route('dashboard.packages.create')"
            :action-label="__('dashboard.actions.create')"
        />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.name') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.packages.price') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.packages.features') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.sort_order') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($packages as $package)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $package->name }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $package->slug }}</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">
                                @if ($package->isPriceOnRequest())
                                    {{-- null, not 0.00 — a different fact,
                                         and the public card says so too. --}}
                                    <span class="text-xs font-bold text-muted-ink">{{ __('dashboard.packages.on_request') }}</span>
                                @else
                                    {{ number_format((float) $package->price, 2) }} {{ $package->currency }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted-ink tabular-nums">{{ count($package->features ?? []) }}</td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">{{ $package->sort_order }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <x-dashboard.badge :tone="$package->is_active ? 'success' : 'muted'">
                                        {{ $package->is_active ? __('dashboard.status.active') : __('dashboard.status.inactive') }}
                                    </x-dashboard.badge>
                                    @if ($package->is_popular)
                                        <x-dashboard.badge tone="warning">{{ __('dashboard.packages.is_popular') }}</x-dashboard.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('dashboard.packages.edit', $package) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.edit') }}
                                    </a>
                                    <x-dashboard.delete-button :action="route('dashboard.packages.destroy', $package)" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $packages->links() }}</div>
    @endif
</x-layouts.app>
