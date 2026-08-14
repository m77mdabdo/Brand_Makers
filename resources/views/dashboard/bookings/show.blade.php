<x-layouts.app :title="$booking->reference">
    <x-slot:header>
        <x-dashboard.page-header
            :title="$booking->scheduledAtLocal()->translatedFormat('l j F Y — H:i')"
            :subtitle="__('dashboard.bookings.reference').': '.$booking->reference"
            :back="route('dashboard.bookings.index')"
        >
            <x-slot:actions>
                <a href="{{ route('dashboard.bookings.agenda', ['date' => $booking->scheduledAtLocal()->toDateString()]) }}"
                    class="inline-flex min-h-[44px] items-center rounded-lg border border-petrol px-4 text-sm font-bold text-petrol transition hover:bg-cream">
                    {{ __('dashboard.bookings.agenda') }}
                </a>
            </x-slot:actions>
        </x-dashboard.page-header>
    </x-slot:header>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-dashboard.card :title="__('dashboard.bookings.who')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'name' => $booking->name,
                        'email' => $booking->email,
                        'phone' => $booking->phone,
                        'received' => $booking->created_at->translatedFormat('j F Y H:i'),
                    ] as $key => $value)
                        @if ($value)
                            <div>
                                <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.fields.'.$key) }}</dt>
                                <dd class="mt-1 text-sm font-bold text-ink" @if (in_array($key, ['email', 'phone'], true)) dir="ltr" @endif>{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.bookings.topic') }}</dt>
                        <dd class="mt-1 text-sm font-bold text-ink">{{ $booking->service?->name ?? __('dashboard.bookings.no_topic') }}</dd>
                    </div>
                </dl>

                @if ($booking->notes)
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-muted-ink">{{ __('dashboard.bookings.notes') }}</dt>
                        {{-- Escaped, always. This is visitor-submitted text. --}}
                        <dd class="mt-1 whitespace-pre-line text-sm text-muted-ink">{{ $booking->notes }}</dd>
                    </div>
                @endif
            </x-dashboard.card>

            <x-dashboard.card :title="__('dashboard.fields.status')">
                <p class="flex flex-wrap items-center gap-3">
                    <x-dashboard.badge :tone="match ($booking->status) {
                        App\Models\Booking::STATUS_CONFIRMED => 'success',
                        App\Models\Booking::STATUS_PENDING => 'warning',
                        default => 'muted',
                    }">
                        {{ __('dashboard.booking_statuses.'.$booking->status) }}
                    </x-dashboard.badge>
                </p>

                @if ($allowedTransitions === [])
                    {{-- Completed and cancelled are terminal. Reopening a
                         cancelled booking would silently re-take a slot the
                         capacity check has since given away. --}}
                    <p class="text-sm text-muted-ink">{{ __('dashboard.bookings.terminal') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($allowedTransitions as $status)
                            {{-- Only the moves this booking can actually
                                 make: the screen offers no button that
                                 would be refused. --}}
                            <form method="POST" action="{{ route('dashboard.bookings.status', $booking) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button
                                    type="submit"
                                    @class([
                                        'inline-flex min-h-[44px] items-center rounded-lg border px-4 text-sm font-bold transition',
                                        'border-petrol bg-petrol text-white hover:bg-petrol-deep' => $status === App\Models\Booking::STATUS_CONFIRMED,
                                        'border-line text-petrol hover:bg-cream' => $status !== App\Models\Booking::STATUS_CONFIRMED,
                                    ])
                                >
                                    {{ __('dashboard.bookings.transition_to.'.$status) }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Stated on the screen where an admin would otherwise
                         assume the visitor has been told. See
                         BookingNotifier. --}}
                    <p class="text-xs text-muted-ink">{{ __('dashboard.bookings.notifications_note') }}</p>
                @endif
            </x-dashboard.card>
        </div>

        <div class="space-y-6">
            <x-dashboard.card :title="__('dashboard.bookings.when')">
                <p class="text-lg font-black text-ink">{{ $booking->scheduledAtLocal()->translatedFormat('l j F Y') }}</p>
                <p class="text-2xl font-black tabular-nums text-orange-ink" dir="ltr">{{ $booking->scheduledAtLocal()->format('H:i') }}</p>
                <p class="text-xs text-muted-ink" dir="ltr">{{ $timezone }}</p>
            </x-dashboard.card>

            <x-dashboard.card :title="__('dashboard.bookings.linked_lead')">
                @if ($booking->lead)
                    {{-- Every booking creates a lead in the same
                         transaction, so this is the enquiry queue's copy of
                         the same person — with the attribution attached. --}}
                    <p class="text-sm text-ink">
                        <x-dashboard.badge tone="neutral">{{ __('dashboard.lead_types.'.$booking->lead->type) }}</x-dashboard.badge>
                    </p>
                    <a href="{{ route('dashboard.leads.show', $booking->lead) }}" class="inline-flex min-h-[44px] items-center text-sm font-bold text-petrol underline underline-offset-2 hover:text-orange-ink">
                        {{ __('dashboard.bookings.view_lead') }}
                    </a>
                @else
                    <p class="text-sm text-muted-ink">{{ __('dashboard.common.none') }}</p>
                @endif
            </x-dashboard.card>
        </div>
    </div>
</x-layouts.app>
