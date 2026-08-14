<x-layouts.app :title="__('dashboard.bookings.agenda')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.bookings.agenda_for', ['date' => $date->translatedFormat('l j F Y')])"
            :subtitle="__('dashboard.bookings.intro', ['timezone' => $timezone])"
            :back="route('dashboard.bookings.index')"
        />
    </x-slot:header>

    {{-- Day navigation. Plain links carrying a date, so every day in the
         agenda is a bookmarkable URL. --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a href="{{ route('dashboard.bookings.agenda', ['date' => $previousDate]) }}"
            class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
            {{ __('dashboard.bookings.previous_day') }}
        </a>
        <a href="{{ route('dashboard.bookings.agenda') }}"
            class="inline-flex min-h-[44px] items-center rounded-lg border border-petrol px-4 text-sm font-bold text-petrol transition hover:bg-cream">
            {{ __('dashboard.bookings.today') }}
        </a>
        <a href="{{ route('dashboard.bookings.agenda', ['date' => $nextDate]) }}"
            class="inline-flex min-h-[44px] items-center rounded-lg border border-line px-4 text-sm font-bold text-petrol transition hover:bg-cream">
            {{ __('dashboard.bookings.next_day') }}
        </a>
    </div>

    @if ($bookings->isEmpty())
        <x-dashboard.empty-state :message="__('dashboard.bookings.agenda_empty')" />
    @else
        <div class="rounded-2xl border border-line bg-white p-4 sm:p-6">
            <ol class="space-y-3">
                @foreach ($bookings as $booking)
                    @php $isCancelled = $booking->status === App\Models\Booking::STATUS_CANCELLED; @endphp

                    {{-- Cancelled appointments are SHOWN, struck through,
                         rather than hidden: an admin looking at a day needs
                         to see that 10:00 was cancelled, not an empty gap
                         they might double-book into by hand. --}}
                    <li @class([
                        'flex flex-wrap items-center gap-4 rounded-xl border p-4',
                        'border-line bg-white' => ! $isCancelled,
                        'border-dashed border-line bg-cream/50' => $isCancelled,
                    ])>
                        <span class="w-16 shrink-0 text-lg font-black tabular-nums text-petrol" dir="ltr">
                            {{ $booking->scheduledAtLocal()->format('H:i') }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span @class(['block font-bold text-ink', 'line-through opacity-60' => $isCancelled])>
                                {{ $booking->name }}
                            </span>
                            <span class="block text-xs text-muted-ink">
                                {{ $booking->service?->name ?? __('dashboard.bookings.no_topic') }}
                                · <span dir="ltr">{{ $booking->reference }}</span>
                            </span>
                        </span>

                        <x-dashboard.badge :tone="match ($booking->status) {
                            App\Models\Booking::STATUS_CONFIRMED => 'success',
                            App\Models\Booking::STATUS_PENDING => 'warning',
                            default => 'muted',
                        }">
                            {{ __('dashboard.booking_statuses.'.$booking->status) }}
                        </x-dashboard.badge>

                        <a href="{{ route('dashboard.bookings.show', $booking) }}" class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-sm font-bold text-petrol transition hover:bg-cream">
                            {{ __('dashboard.actions.view') }}
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</x-layouts.app>
