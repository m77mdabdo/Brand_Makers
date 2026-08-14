@php
    // The seeded week starts on Sunday, matching Carbon::dayOfWeek and the
    // day_of_week column, so no translation layer sits between the form
    // and the date maths.
    $dayNames = __('dashboard.booking_availability.days');
@endphp

<x-layouts.app :title="__('dashboard.booking_availability.title')">
    <x-slot:header>
        <x-dashboard.page-header
            :title="__('dashboard.booking_availability.title')"
            :subtitle="__('dashboard.booking_availability.intro', ['timezone' => $timezone])"
        />
    </x-slot:header>

    @if ($week->every(fn ($day) => ! $day->is_open))
        {{-- The state of an install where nobody has opened a day. Saying
             so here is the difference between "booking is broken" and
             "nobody has configured it" — the public page shows its
             not-open notice for exactly as long as this is true. --}}
        <div role="status" class="mb-6 rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm text-ink">
            {{ __('dashboard.booking_availability.not_open_warning') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- --------------------------------------------------------------
             Weekly hours
        -------------------------------------------------------------- --}}
        <form method="POST" action="{{ route('dashboard.booking-availability.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-dashboard.card :title="__('dashboard.booking_availability.weekly')" :subtitle="__('dashboard.booking_availability.weekly_help')">
                <p class="text-xs text-muted-ink">
                    {{ __('dashboard.booking_availability.window_note', ['days' => $windowDays, 'hours' => $noticeHours]) }}
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-sm">
                        <thead class="border-b border-line bg-cream/60">
                            <tr>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.fields.name') }}</th>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.booking_availability.is_open') }}</th>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.booking_availability.opens_at') }}</th>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.booking_availability.closes_at') }}</th>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.booking_availability.slot_minutes') }}</th>
                                <th class="px-3 py-3 text-start font-extrabold">{{ __('dashboard.booking_availability.capacity') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($week as $day)
                                @php $i = $day->day_of_week; @endphp
                                <tr>
                                    <td class="px-3 py-3 font-bold text-ink">{{ $dayNames[$i] }}</td>
                                    <td class="px-3 py-3">
                                        {{-- The hidden 0 is what makes an
                                             unticked box submit at all;
                                             without it "absent" would be
                                             indistinguishable from "not on
                                             this form". --}}
                                        <input type="hidden" name="days[{{ $i }}][is_open]" value="0">
                                        <label class="inline-flex min-h-[44px] items-center">
                                            <input
                                                type="checkbox"
                                                name="days[{{ $i }}][is_open]"
                                                value="1"
                                                @checked(old("days.{$i}.is_open", $day->is_open))
                                                class="h-5 w-5 rounded border-line text-orange-ink shadow-sm focus:ring-petrol"
                                            >
                                            <span class="sr-only">{{ $dayNames[$i] }} — {{ __('dashboard.booking_availability.is_open') }}</span>
                                        </label>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="time"
                                            name="days[{{ $i }}][opens_at]"
                                            dir="ltr"
                                            value="{{ old("days.{$i}.opens_at", substr((string) $day->opens_at, 0, 5)) }}"
                                            class="block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                        >
                                        <x-input-error :messages="$errors->get(\"days.{$i}.opens_at\")" class="mt-1" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="time"
                                            name="days[{{ $i }}][closes_at]"
                                            dir="ltr"
                                            value="{{ old("days.{$i}.closes_at", substr((string) $day->closes_at, 0, 5)) }}"
                                            class="block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                        >
                                        <x-input-error :messages="$errors->get(\"days.{$i}.closes_at\")" class="mt-1" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="number" min="5" max="480" step="5"
                                            name="days[{{ $i }}][slot_minutes]"
                                            dir="ltr"
                                            value="{{ old("days.{$i}.slot_minutes", $day->slot_minutes) }}"
                                            class="block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                        >
                                        <x-input-error :messages="$errors->get(\"days.{$i}.slot_minutes\")" class="mt-1" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <input
                                            type="number" min="1" max="50"
                                            name="days[{{ $i }}][capacity]"
                                            dir="ltr"
                                            value="{{ old("days.{$i}.capacity", $day->capacity) }}"
                                            class="block min-h-[44px] w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                        >
                                        <x-input-error :messages="$errors->get(\"days.{$i}.capacity\")" class="mt-1" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-dashboard.card>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('dashboard.common.save') }}</button>
            </div>
        </form>

        {{-- --------------------------------------------------------------
             One-off closures
        -------------------------------------------------------------- --}}
        <x-dashboard.card :title="__('dashboard.booking_availability.blackouts')" :subtitle="__('dashboard.booking_availability.blackouts_help')">
            <form method="POST" action="{{ route('dashboard.booking-availability.blackouts.store') }}" class="grid gap-4 sm:grid-cols-3">
                @csrf

                <x-dashboard.field name="date" type="date" dir="ltr" :label="__('dashboard.booking_availability.blackout_date')" required />
                <x-dashboard.field name="reason.ar" :label="__('dashboard.booking_availability.blackout_reason').' ('.__('dashboard.common.arabic').')'" />
                <x-dashboard.field name="reason.en" :label="__('dashboard.booking_availability.blackout_reason').' ('.__('dashboard.common.english').')'" />

                <div class="sm:col-span-3">
                    <button type="submit" class="btn btn-primary !px-5 !py-2.5 !text-sm">
                        {{ __('dashboard.booking_availability.blackout_add') }}
                    </button>
                </div>
            </form>

            @if ($blackouts->isEmpty())
                <p class="text-sm text-muted-ink">{{ __('dashboard.booking_availability.blackouts_empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-sm">
                        <tbody class="divide-y divide-line">
                            @foreach ($blackouts as $blackout)
                                <tr>
                                    <td class="py-3 font-bold text-ink" dir="ltr">
                                        {{ $blackout->date->translatedFormat('j F Y') }}
                                        @if ($blackout->date->lessThan($today))
                                            {{-- Kept as a record but no
                                                 longer affecting anything
                                                 the generator produces. --}}
                                            <x-dashboard.badge tone="muted">{{ __('dashboard.booking_availability.past') }}</x-dashboard.badge>
                                        @endif
                                    </td>
                                    <td class="py-3 text-muted-ink">{{ $blackout->reason ?: __('dashboard.common.none') }}</td>
                                    <td class="py-3 text-end">
                                        <x-dashboard.delete-button :action="route('dashboard.booking-availability.blackouts.destroy', $blackout)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>{{ $blackouts->links() }}</div>
            @endif
        </x-dashboard.card>
    </div>
</x-layouts.app>
