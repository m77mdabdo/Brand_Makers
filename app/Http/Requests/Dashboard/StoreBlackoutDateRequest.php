<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlackoutDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * No `unique` rule: blacking out a day that is already blacked
             * out is not a mistake, it is a restatement — and possibly a
             * better reason. The service upserts on the date, so the
             * second submission updates the first rather than erroring.
             *
             * No `after:today` either. A studio correcting last week's
             * calendar is doing something legitimate, and a past blackout
             * affects nothing the generator produces anyway.
             */
            'date' => ['required', 'date_format:Y-m-d'],

            // Optional: "closed" is a complete statement on its own, and
            // forcing a reason would produce a table full of "holiday".
            'reason' => ['nullable', 'array'],
            'reason.ar' => ['nullable', 'string', 'max:255'],
            'reason.en' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => __('dashboard.booking_availability.blackout_date'),
            'reason.ar' => __('dashboard.booking_availability.blackout_reason').' ('.__('dashboard.common.arabic').')',
            'reason.en' => __('dashboard.booking_availability.blackout_reason').' ('.__('dashboard.common.english').')',
        ];
    }
}
