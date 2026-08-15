<?php

namespace App\Services\Orders;

use App\Models\Order;

/**
 * The portal's front door, and the only place an untrusted pair of
 * credentials becomes an order.
 *
 * THE SECURITY MODEL, in the order it matters:
 *
 * 1. THE LOOKUP IS BY CODE, NEVER BY REFERENCE. The query is a single
 *    indexed equality on tracking_code_hash. The reference is short,
 *    printed on invoices and read aloud over the phone — treating it as
 *    the thing being searched for would make the portal enumerable by
 *    construction. It is checked SECOND, as a confirmation that the person
 *    holding the code also knows which order they are opening.
 *
 * 2. THE RAW CODE IS NEVER COMPARED. Only SHA-256 digests meet, and the
 *    plaintext exists in this process for the length of one request. A
 *    database dump yields no working portal links.
 *
 * 3. EVERY FAILURE IS THE SAME FAILURE. Wrong code, wrong reference,
 *    unknown reference, malformed input, soft-deleted order — all return
 *    null, and the controller renders one message. Nothing an attacker can
 *    observe distinguishes "no such order" from "wrong code", so the
 *    portal cannot be used to discover which references exist.
 *
 * 4. THE WORK IS THE SAME EITHER WAY. A malformed code would otherwise
 *    skip the query entirely and answer measurably faster than a
 *    well-formed miss — so the digest and the query are performed
 *    regardless, and the reference comparison uses hash_equals(). See
 *    find() for the detail.
 */
class OrderLookupService
{
    /**
     * The order matching BOTH credentials, or null.
     *
     * Note the shape: one query, one comparison, one return. There is no
     * branch that returns early with less work done, because a branch like
     * that is a timing signal.
     */
    public function find(?string $reference, ?string $code): ?Order
    {
        $reference = $this->normaliseReference($reference);
        $code = is_string($code) ? trim($code) : '';

        /*
         * The digest is computed even for input that cannot possibly
         * match. hash() on a short string is nanoseconds, but skipping it
         * would make a malformed submission observably cheaper than a
         * well-formed one — and "your code is the wrong SHAPE" is more
         * than a stranger should be able to learn without asking.
         */
        $hash = Order::hashTrackingCode($code);

        /*
         * withTrashed() is NOT used. An archived order is gone as far as
         * the portal is concerned, and it degrades to the same generic
         * failure as everything else rather than to a different message.
         */
        $order = Order::query()->where('tracking_code_hash', $hash)->first();

        if ($order === null) {
            return null;
        }

        /*
         * hash_equals rather than ===, on values that are already known
         * to be equal-length-ish. This is a credential comparison in all
         * but name: an attacker who holds a valid code for order A and is
         * guessing at B's reference would otherwise get a timing gradient
         * as their guess shares a longer prefix.
         */
        if (! hash_equals($this->normaliseReference($order->reference), $reference)) {
            return null;
        }

        return $order;
    }

    /**
     * Reduces a reference to the characters that carry meaning, so both
     * sides of the comparison are measured the same way.
     *
     * A reference is read aloud and typed off an invoice. The commonest
     * transcriptions of "CM-2026-7QK4TP" are with spaces instead of the
     * dashes, with no separators at all, and in lower case — none of which
     * is a mistake worth a failure the client cannot diagnose. Stripping
     * every non-alphanumeric character and upper-casing makes all of them
     * equal to the stored value, WHICH IS WHY THE STORED SIDE IS
     * NORMALISED TOO: stripping only the input would turn "CM 2026 7QK4TP"
     * into "CM20267QK4TP" and then compare it against a value that still
     * has its dashes, which is exactly the bug this comment used to
     * describe as a feature.
     */
    private function normaliseReference(?string $reference): string
    {
        if (! is_string($reference)) {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $reference) ?? '');
    }
}
