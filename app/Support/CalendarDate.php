<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Turning a request-supplied "Y-m-d" into a real date, or into nothing.
 *
 * WHY THIS EXISTS. A shape check is not a validity check. "2026-13-45"
 * matches /^\d{4}-\d{2}-\d{2}$/ perfectly and is not a date — there is no
 * thirteenth month — and handing it to Carbon::parse() throws
 * InvalidFormatException, which for a query-string parameter means a 500
 * on a URL somebody typed.
 *
 * WHY IT LIVES IN Support RATHER THAN Services\Booking, where it started.
 * Every dashboard list that filters by a date range needs exactly this
 * check, and three of them had drifted apart: booking used the strict
 * parse while orders and visitor sessions still used a bare shape regex,
 * so a typed "2026-13-45" quietly returned an empty list on two screens
 * and a correct one on the third. Leaving it under Booking would have
 * meant the orders module importing from the booking module to validate a
 * date, which is the kind of dependency that gets copy-pasted around
 * instead of reused.
 *
 * WHY NOT CarbonImmutable::hasFormat(), which this class used to rely on.
 * It is not strict enough, and the difference is silent:
 *
 *     hasFormat('2026-13-45', 'Y-m-d')  →  false   (correctly rejected)
 *     hasFormat('2026-02-31', 'Y-m-d')  →  TRUE    (there is no 31 February)
 *     createFromFormat('!Y-m-d', '2026-02-31')  →  2026-03-03
 *
 * So a filter for the 31st of February silently became the 3rd of March
 * and the admin was shown a different day than the one they asked for —
 * which is worse than the empty list this class was introduced to prevent.
 * The round-trip below is the actual check: parse it, format it back, and
 * require the two to be identical. Anything PHP had to "correct" fails.
 */
final class CalendarDate
{
    /**
     * The date at the START of the day in the given zone, or null when the
     * input is not a real calendar date.
     */
    public static function parse(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            // '!' zeroes every field the format does not set, so the time
            // is midnight rather than "now" — otherwise two calls a second
            // apart would produce different instants for the same date.
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        } catch (\Throwable) {
            return null;
        }

        // THE ROUND TRIP. createFromFormat rolls impossible dates forward
        // rather than failing, so this is what distinguishes a date PHP
        // read from one PHP invented.
        return $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }

    /**
     * Whether the value is a real Y-m-d date. For the filter whitelists,
     * which drop what they cannot use rather than parsing it.
     *
     * Defined in terms of parse() so the two can never disagree about what
     * counts as valid — which is exactly how the shape-check drift
     * happened in the first place.
     */
    public static function isValid(mixed $value): bool
    {
        return self::parse($value, 'UTC') !== null;
    }
}
