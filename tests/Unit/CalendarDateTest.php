<?php

namespace Tests\Unit;

use App\Support\CalendarDate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The strict date parser every dashboard filter now shares.
 *
 * It exists because a shape check is not a validity check, and it is
 * tested directly because the previous implementation — CarbonImmutable::
 * hasFormat() — passed "2026-02-31" and silently produced 3 March. The
 * class docblock asserted the opposite, and nothing checked.
 */
class CalendarDateTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function realDates(): array
    {
        return [
            'an ordinary day' => ['2026-09-02'],
            'the first of a month' => ['2026-01-01'],
            'the last of a 31-day month' => ['2026-12-31'],
            'the last of a 30-day month' => ['2026-04-30'],
            'a leap day in a leap year' => ['2024-02-29'],
        ];
    }

    #[DataProvider('realDates')]
    public function test_a_real_date_parses(string $value): void
    {
        $this->assertTrue(CalendarDate::isValid($value));
        $this->assertSame($value, CalendarDate::parse($value, 'UTC')?->toDateString());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonDates(): array
    {
        return [
            'a thirteenth month' => ['2026-13-45'],
            'a zero month' => ['2026-00-10'],
            'a zero day' => ['2026-01-00'],
            'the 31st of February' => ['2026-02-31'],
            'a leap day in a common year' => ['2026-02-29'],
            'the 31st of a 30-day month' => ['2026-04-31'],
            'a 32nd day' => ['2026-12-32'],
            'unpadded parts' => ['2026-1-5'],
            'a word' => ['yesterday'],
            'a slashed date' => ['02/09/2026'],
            'a datetime' => ['2026-09-02 10:00:00'],
            'an injection attempt' => ["2026-09-02' OR 1=1--"],
            'empty' => [''],
            'null' => [null],
            'an integer' => [20260902],
            'an array' => [['2026-09-02']],
        ];
    }

    #[DataProvider('nonDates')]
    public function test_anything_that_is_not_a_real_date_is_refused(mixed $value): void
    {
        $this->assertFalse(CalendarDate::isValid($value));
        $this->assertNull(CalendarDate::parse($value, 'UTC'));
    }

    public function test_a_rolled_over_date_never_becomes_a_different_day(): void
    {
        // The bug this class was rewritten to fix: createFromFormat rolls
        // 2026-02-31 forward to 2026-03-03 rather than failing, so an
        // admin filtering for a date that does not exist was shown a
        // different day than the one they asked for.
        $this->assertNull(CalendarDate::parse('2026-02-31', 'UTC'));
    }

    public function test_the_parsed_date_starts_at_midnight_in_the_given_zone(): void
    {
        $date = CalendarDate::parse('2026-09-02', 'Asia/Riyadh');

        $this->assertSame('2026-09-02 00:00:00', $date->toDateTimeString());
        $this->assertSame('Asia/Riyadh', $date->timezoneName);
        // Midnight Riyadh is 21:00 UTC the previous day — the conversion
        // the booking module depends on.
        $this->assertSame('2026-09-01 21:00:00', $date->utc()->toDateTimeString());
    }

    public function test_two_calls_a_moment_apart_produce_the_same_instant(): void
    {
        // The '!' in the format zeroes every unset field; without it the
        // time would default to "now" and the same date would parse to
        // different instants.
        $this->assertTrue(
            CalendarDate::parse('2026-09-02', 'UTC')->equalTo(CalendarDate::parse('2026-09-02', 'UTC'))
        );
    }

    public function test_is_valid_and_parse_cannot_disagree(): void
    {
        // isValid() is defined in terms of parse() precisely so the two
        // cannot drift — which is how the original shape-check divergence
        // happened across three services.
        foreach (['2026-09-02', '2026-02-31', 'yesterday', '2024-02-29', ''] as $value) {
            $this->assertSame(
                CalendarDate::isValid($value),
                CalendarDate::parse($value, 'UTC') !== null,
                "isValid() and parse() disagree about [{$value}]."
            );
        }
    }
}
