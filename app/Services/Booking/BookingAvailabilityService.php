<?php

namespace App\Services\Booking;

use App\Models\BookingAvailability;
use App\Models\BookingBlackoutDate;
use App\Services\Cms\HtmlSanitizerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes the weekly booking window and the one-off closures.
 *
 * ARBITRARY-KEY SAFE BY ITERATING THE WEEK, NOT THE PAYLOAD. update()
 * loops over the seven known weekdays and pulls each one's values OUT of
 * the submitted array by index — the same shape as SettingsService, and
 * for the same reason. A payload carrying `days[9]` or
 * `days[1][created_at]` is never read, let alone written, even if
 * validation were later loosened.
 *
 * Every field is written for every day, including the closed ones, so a
 * day that is switched off keeps the hours it had. An admin reopening
 * Friday should find last month's hours waiting rather than a blank form.
 */
class BookingAvailabilityService
{
    /**
     * The only per-day keys this service reads.
     *
     * @var list<string>
     */
    private const WRITABLE = ['is_open', 'opens_at', 'closes_at', 'slot_minutes', 'capacity'];

    public function __construct(private readonly HtmlSanitizerService $sanitizer) {}

    /**
     * The full week, in weekday order, creating any missing row.
     *
     * The editor renders seven days whatever the table holds, so an
     * install whose seeder has not run — or a row deleted by hand — still
     * gets a complete form rather than a week with gaps.
     *
     * @return Collection<int, BookingAvailability>
     */
    public function week(): Collection
    {
        $existing = BookingAvailability::query()->get()->keyBy('day_of_week');

        return collect(range(0, 6))->map(fn (int $day) => $existing->get($day) ?? BookingAvailability::create([
            'day_of_week' => $day,
            'is_open' => false,
            'opens_at' => '09:00:00',
            'closes_at' => '17:00:00',
            'slot_minutes' => 45,
            'capacity' => 1,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $days  the validated `days` payload
     */
    public function update(array $days): void
    {
        DB::transaction(function () use ($days) {
            for ($day = 0; $day <= 6; $day++) {
                $submitted = is_array($days[$day] ?? null) ? $days[$day] : [];

                $attributes = array_intersect_key($submitted, array_flip(self::WRITABLE));

                // A checkbox is absent from the payload entirely when
                // unticked, so "not submitted" IS false here rather than
                // "leave alone" — the same rule SettingsService follows.
                $attributes['is_open'] = (bool) ($submitted['is_open'] ?? false);

                foreach (['opens_at', 'closes_at'] as $field) {
                    if (array_key_exists($field, $attributes)) {
                        $attributes[$field] = $this->normaliseTime($attributes[$field]);
                    }
                }

                foreach (['slot_minutes', 'capacity'] as $field) {
                    if (array_key_exists($field, $attributes)) {
                        $attributes[$field] = (int) $attributes[$field];
                    }
                }

                BookingAvailability::updateOrCreate(['day_of_week' => $day], $attributes);
            }
        });
    }

    /**
     * Adds a one-off closure.
     *
     * updateOrCreate on the date rather than create: `date` is unique, and
     * an admin blacking out a day that is already blacked out has not made
     * a mistake worth a validation error — they have restated a fact, and
     * possibly given it a better reason.
     *
     * @param  array<string, mixed>|null  $reason  per-locale
     */
    public function blackout(string $date, ?array $reason = null): BookingBlackoutDate
    {
        return BookingBlackoutDate::updateOrCreate(
            ['date' => BookingDate::parse($date, config('booking.timezone'))?->toDateString() ?? $date],
            // Plain text, never rich: the reason is a caption on a
            // dashboard row and on nothing else.
            ['reason' => $this->sanitizer->sanitizeTranslations($reason, false)],
        );
    }

    public function removeBlackout(BookingBlackoutDate $date): void
    {
        $date->delete();
    }

    /**
     * "HH:MM" or "HH:MM:SS" to the column's "HH:MM:SS".
     *
     * A time input submits "09:00"; the column is TIME. Normalising here
     * means a comparison between a stored value and a freshly submitted
     * one never fails on the seconds.
     */
    private function normaliseTime(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches) !== 1) {
            return '09:00:00';
        }

        return sprintf('%02d:%02d:00', min(23, (int) $matches[1]), min(59, (int) $matches[2]));
    }
}
