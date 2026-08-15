<x-layouts.app :title="__('dashboard.resources.orders')">
    <x-slot:header>
        <x-dashboard.page-header :title="__('dashboard.resources.orders')" :subtitle="__('dashboard.orders.intro')">
            <x-slot:actions>
                <a href="{{ route('dashboard.orders.create') }}" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                    {{ __('dashboard.actions.create') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    <form method="GET" action="{{ route('dashboard.orders.index') }}" class="mb-6 rounded-2xl border border-line bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard.field
                name="status"
                :label="__('dashboard.fields.status')"
                :value="$filters['status'] ?? null"
                :options="['' => __('dashboard.common.all')] + collect($statuses)->mapWithKeys(fn ($s) => [$s => __('dashboard.order_statuses.'.$s)])->all()"
            />
            <x-dashboard.field name="reference" dir="ltr" :label="__('dashboard.orders.reference')" :value="$filters['reference'] ?? null" />
            <x-dashboard.field name="date_from" type="date" dir="ltr" :label="__('dashboard.fields.date_from')" :value="$filters['date_from'] ?? null" />
            <x-dashboard.field name="date_to" type="date" dir="ltr" :label="__('dashboard.fields.date_to')" :value="$filters['date_to'] ?? null" />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.actions.filter') }}</button>
            <a href="{{ route('dashboard.orders.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                {{ __('dashboard.actions.reset') }}
            </a>
        </div>
    </form>

    @if ($orders->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.orders.empty')" :action="route('dashboard.orders.create')" :action-label="__('dashboard.actions.create')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.orders.reference') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.orders.client') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.resources.packages') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.orders.progress') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.orders.placed_at') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-bold text-ink" dir="ltr">{{ $order->reference }}</td>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $order->client_name }}</span>
                                @if ($order->client_email)
                                    <span class="block text-xs text-muted-ink" dir="ltr">{{ $order->client_email }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted-ink">{{ $order->package?->name ?? __('dashboard.common.none') }}</td>
                            <td class="px-4 py-3 tabular-nums" dir="ltr">{{ $order->progress }}%</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="match ($order->status) {
                                    App\Models\Order::STATUS_COMPLETED => 'success',
                                    App\Models\Order::STATUS_CANCELLED => 'muted',
                                    App\Models\Order::STATUS_PENDING => 'warning',
                                    default => 'neutral',
                                }">
                                    {{ __('dashboard.order_statuses.'.$order->status) }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-ink">{{ $order->placed_at->translatedFormat('j M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('dashboard.orders.show', $order) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.view') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-layouts.app>
