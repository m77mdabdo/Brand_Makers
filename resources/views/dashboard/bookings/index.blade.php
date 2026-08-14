<x-layouts.app :title="__('dashboard.resources.bookings')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.resources.bookings')"
            :subtitle="__('dashboard.bookings.intro', ['timezone' => $timezone])"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.bookings.agenda') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-petrol px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                    {{ __('dashboard.bookings.agenda') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    {{-- GET, so a filtered view is a shareable URL and the back button
         works. withQueryString() on the paginator keeps the filters
         across pages. --}}
    <form method="GET" action="{{ route('dashboard.bookings.index') }}" class="mb-6 rounded-2xl border border-line bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-dashboard.field
                name="status"
                :label="__('dashboard.fields.status')"
                :value="$filters['status'] ?? null"
                :options="['' => __('dashboard.common.all')] + collect($statuses)->mapWithKeys(fn ($s) => [$s => __('dashboard.booking_statuses.'.$s)])->all()"
            />

            <x-dashboard.field
                name="service_id"
                :label="__('dashboard.bookings.topic')"
                :value="$filters['service_id'] ?? null"
                :options="['' => __('dashboard.common.all')] + $services->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()"
            />

            <x-dashboard.field name="date_from" type="date" dir="ltr" :label="__('dashboard.fields.date_from')" :value="$filters['date_from'] ?? null" />
            <x-dashboard.field name="date_to" type="date" dir="ltr" :label="__('dashboard.fields.date_to')" :value="$filters['date_to'] ?? null" />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">{{ __('dashboard.actions.filter') }}</button>
            <a href="{{ route('dashboard.bookings.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                {{ __('dashboard.actions.reset') }}
            </a>
        </div>
    </form>

    @if ($bookings->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.bookings.empty')" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="border-b border-line bg-cream/60">
                    <tr>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.bookings.when') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.bookings.who') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.bookings.topic') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.bookings.reference') }}</th>
                        <th class="px-4 py-3 text-start font-extrabold">{{ __('dashboard.fields.status') }}</th>
                        <th class="px-4 py-3 text-end font-extrabold">{{ __('dashboard.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($bookings as $booking)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{-- scheduledAtLocal(), never the raw
                                     column: scheduled_at is UTC, and
                                     printing it would show an admin a time
                                     three hours before the appointment. --}}
                                <span class="font-bold text-ink">{{ $booking->scheduledAtLocal()->translatedFormat('j M Y') }}</span>
                                <span class="block text-xs tabular-nums text-muted-ink" dir="ltr">{{ $booking->scheduledAtLocal()->format('H:i') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-bold text-ink">{{ $booking->name }}</span>
                                <span class="block text-xs text-muted-ink" dir="ltr">{{ $booking->email }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted-ink">
                                {{ $booking->service?->name ?? __('dashboard.bookings.no_topic') }}
                            </td>
                            <td class="px-4 py-3 text-xs tabular-nums text-muted-ink" dir="ltr">{{ $booking->reference }}</td>
                            <td class="px-4 py-3">
                                <x-dashboard.badge :tone="match ($booking->status) {
                                    App\Models\Booking::STATUS_CONFIRMED => 'success',
                                    App\Models\Booking::STATUS_PENDING => 'warning',
                                    default => 'muted',
                                }">
                                    {{ __('dashboard.booking_statuses.'.$booking->status) }}
                                </x-dashboard.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('dashboard.bookings.show', $booking) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                                        {{ __('dashboard.actions.view') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $bookings->links() }}</div>
    @endif
</x-layouts.app>
