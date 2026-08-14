<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking timezone
    |--------------------------------------------------------------------------
    |
    | The studio's wall clock. Every time a visitor sees, every opening hour
    | in booking_availability, and every blackout date is expressed in THIS
    | zone — while bookings.scheduled_at is stored in the application
    | timezone (UTC) like every other timestamp in the schema.
    |
    | WHY THE SPLIT rather than simply setting app.timezone to Asia/Riyadh:
    | app.timezone governs every timestamp in the application, including
    | created_at on tables that have nothing to do with appointments, and
    | changing it would reinterpret rows that were written under the old
    | one. Storing instants in UTC and converting at the edges is the
    | arrangement that stays correct if this studio ever books a client in
    | another zone.
    |
    | Saudi Arabia observes no daylight saving and has held a fixed +03:00
    | since 1947, so there is no ambiguous or skipped local hour to handle —
    | which is the only reason the conversion below can stay this simple.
    | That is a fact about this zone, not an assumption the code makes: the
    | conversion goes through Carbon either way.
    |
    */

    'timezone' => env('BOOKING_TIMEZONE', 'Asia/Riyadh'),

    /*
    |--------------------------------------------------------------------------
    | Notification hook
    |--------------------------------------------------------------------------
    |
    | NOT IMPLEMENTED YET, and deliberately so — see
    | App\Services\Booking\BookingNotifier for the seam a confirmation
    | email or WhatsApp message plugs into.
    |
    | This flag exists so the wiring can be switched on in one place once a
    | mail transport and a WhatsApp provider are actually configured, and
    | so that until then nothing pretends a visitor was notified. The
    | confirmation screen tells the visitor the studio will be in touch, and
    | that sentence stays true because nothing claims a message was sent.
    |
    */

    'notifications_enabled' => env('BOOKING_NOTIFICATIONS_ENABLED', false),

];
