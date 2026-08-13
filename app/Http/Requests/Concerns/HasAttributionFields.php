<?php

namespace App\Http\Requests\Concerns;

/**
 * The hidden fields every public form carries, and the honeypot.
 *
 * Shared so the three forms cannot drift — and so the honeypot's ABSENCE
 * from the rules is stated once, in the one place someone would go
 * looking to "fix" it.
 */
trait HasAttributionFields
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function attributionRules(): array
    {
        return [
            /*
             * Client-supplied JSON. Only checked to be a string of
             * bounded length here — AttributionService does the real
             * work, discarding anything that is not a flat map of
             * scalars. A validation failure here would reject a
             * legitimate enquiry because of a mangled cookie, which is
             * the wrong trade: the enquiry matters, the attribution is
             * nice to have.
             */
            'first_touch_snapshot' => ['nullable', 'string', 'max:4000'],
            'latest_touch_snapshot' => ['nullable', 'string', 'max:4000'],

            /*
             * The honeypot (App\Support\Honeypot::FIELD) is
             * DELIBERATELY NOT VALIDATED and never appears in these
             * rules.
             *
             * A rule like `prohibited` would return a 422 with an error
             * naming the field, which tells a bot author exactly which
             * input to leave alone next time. Instead the controller
             * checks it before any write and returns the SAME success
             * response a real submission gets — so a caught bot learns
             * nothing, and nothing is stored.
             */
        ];
    }
}
