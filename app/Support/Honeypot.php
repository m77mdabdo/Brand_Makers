<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The spam trap shared by every public form.
 *
 * A class rather than a constant on the HasAttributionFields trait: PHP
 * does not allow reading a trait constant through the trait's own name,
 * only through a class that uses it — so the field name would have had to
 * be reached via one arbitrary Form Request, which reads as a mistake.
 */
final class Honeypot
{
    /**
     * Deliberately plausible. A field called "honeypot" is one a bot
     * author reads and skips.
     */
    public const FIELD = 'website_url';

    /**
     * True when the trap was filled — i.e. this is almost certainly a bot.
     *
     * The caller must respond with the SAME success it would give a real
     * submission. An error, a different status, or a measurably different
     * response tells a bot author exactly which field to leave alone next
     * time, and the trap stops working.
     */
    public static function tripped(Request $request): bool
    {
        return $request->filled(self::FIELD);
    }
}
