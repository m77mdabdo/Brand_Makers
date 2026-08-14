<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;

/**
 * Turning a request-supplied "Y-m-d" into a real date, or into nothing.
 *
 * WHY THIS EXISTS. A shape check is not a validity check. "2026-13-45"
 * matches /^\d{4}-\d{2}-\d{2}$/ perfectly and is not a date — there is no
 * thirteenth month — and handing it to Carbon::parse() throws
 * InvalidFormatException, which for a query-string parameter means a 500
 * on a URL somebody typed. Three places take a date from a request (the
 * slot lookup, the dashboard filters and the agenda's day navigation), so
 * the strict parse lives once, here, rather than being remembered three
 * times.
 *
 * hasFormat() is the strict check: it re-formats what it parsed and
 * compares, so a rolled-over date like "2026-02-31" (which PHP would
 * happily read as 3 March) is rejected rather than silently becoming a
 * different day than the one asked for.
 */
final class BookingDate
{
    /**
     * The date at the START of the day in the given zone, or null when the
     * input is not a real calendar date.
     */
    public static function parse(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || ! CarbonImmutable::hasFormat($value, 'Y-m-d')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone)->startOfDay();
    }

    /**
     * Whether the value is a real Y-m-d date. For the filter whitelists,
     * which drop what they cannot use rather than parsing it.
     */
    public static function isValid(mixed $value): bool
    {
        return is_string($value) && CarbonImmutable::hasFormat($value, 'Y-m-d');
    }
}
