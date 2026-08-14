<?php

namespace App\Services\Booking;

use RuntimeException;

/**
 * The slot went while the visitor was filling in the form.
 *
 * A distinct exception rather than a false return, because the two
 * failures the controller has to tell apart — "that is not a slot at all"
 * and "that was a slot until a moment ago" — deserve different messages.
 * The first is a stale page or a crafted payload; the second is an
 * ordinary race between two people, and the visitor should be told to pick
 * again rather than shown a validation error implying they did something
 * wrong.
 */
class SlotUnavailableException extends RuntimeException {}
