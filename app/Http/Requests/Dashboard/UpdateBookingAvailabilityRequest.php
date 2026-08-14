<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBookingAvailabilityRequest extends FormRequest
{
    /**
     * The route already carries ['auth', 'admin'], so authorisation here
     * is a deliberate pass-through rather than an omission.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules are generated PER WEEKDAY, 0 to 6.
     *
     * A key outside that range has no rule, so validated() drops it — and
     * BookingAvailabilityService iterates the same seven indexes rather
     * than the payload, so it would never be read anyway. Two independent
     * layers, the same arrangement SettingsService uses.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['days' => ['required', 'array']];

        for ($day = 0; $day <= 6; $day++) {
            $rules["days.{$day}"] = ['required', 'array'];
            $rules["days.{$day}.is_open"] = ['nullable', 'boolean'];
            $rules["days.{$day}.opens_at"] = ['required', 'date_format:H:i'];
            $rules["days.{$day}.closes_at"] = ['required', 'date_format:H:i'];
            // A slot shorter than 5 minutes is not an appointment, and one
            // longer than a day cannot fit in one. The upper bound also
            // stops the generator being handed something that produces no
            // slots at all without saying why.
            $rules["days.{$day}.slot_minutes"] = ['required', 'integer', 'min:5', 'max:480'];
            $rules["days.{$day}.capacity"] = ['required', 'integer', 'min:1', 'max:50'];
        }

        return $rules;
    }

    /**
     * A day that is open must have a window that can actually hold a slot.
     *
     * Checked here rather than in the service because it is a relationship
     * BETWEEN three fields, and the admin needs to be told which day is
     * wrong. Without it an open day with closes_at before opens_at, or a
     * 60-minute slot in a 30-minute window, would save happily and then
     * silently offer nothing — the hardest kind of misconfiguration to
     * diagnose, because the public page looks merely quiet.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $days = $this->input('days', []);

            for ($day = 0; $day <= 6; $day++) {
                $config = $days[$day] ?? [];

                if (! (bool) ($config['is_open'] ?? false)) {
                    continue;
                }

                $opens = $this->minutes($config['opens_at'] ?? null);
                $closes = $this->minutes($config['closes_at'] ?? null);
                $length = (int) ($config['slot_minutes'] ?? 0);

                if ($opens === null || $closes === null) {
                    continue;
                }

                if ($closes <= $opens) {
                    $validator->errors()->add("days.{$day}.closes_at", __('dashboard.booking_availability.closes_before_opens'));

                    continue;
                }

                if ($length > $closes - $opens) {
                    $validator->errors()->add("days.{$day}.slot_minutes", __('dashboard.booking_availability.slot_longer_than_window'));
                }
            }
        });
    }

    private function minutes(mixed $time): ?int
    {
        if (! is_string($time) || preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
