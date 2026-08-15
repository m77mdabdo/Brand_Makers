<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * SHAPE ONLY, and deliberately loose.
     *
     * NOTE WHAT IS NOT HERE: no `exists` rule on the reference, and no
     * pattern requiring the tracking code to be 64 hex characters.
     *
     * An `exists` rule would answer "does this reference exist" with a
     * field-level validation error — which is precisely the oracle the
     * whole portal is built to deny. And rejecting a malformed code at
     * validation would give a measurably different response from a
     * well-formed wrong one, splitting the single generic failure into
     * two distinguishable outcomes.
     *
     * So everything that can fail, fails the same way: in
     * OrderLookupService, into one message.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:64'],
            // 64 hex characters is the real length; the ceiling here is
            // only a bound on what reaches a hash function.
            'tracking_code' => ['required', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference' => __('site.tracker.reference'),
            'tracking_code' => __('site.tracker.code'),
        ];
    }
}
