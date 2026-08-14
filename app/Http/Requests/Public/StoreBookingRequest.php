<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\HasAttributionFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    use HasAttributionFields;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],

            /*
             * The topic. Constrained to an ACTIVE service, so a withdrawn
             * one cannot be booked from a stale page — and nullable,
             * because "I am not sure yet" is a legitimate answer and
             * forcing a choice would cost bookings.
             */
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('is_active', true)],

            /*
             * SHAPE ONLY, deliberately.
             *
             * Whether this date and time are actually a bookable slot is
             * not a validation question — it depends on the weekly
             * availability, the blackout dates, the notice period and how
             * many people have already booked, all of which
             * BookingSlotService::resolveSlot() re-derives. A rule here
             * would either duplicate that logic or trust the client's word
             * for it.
             */
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'time' => ['required', 'string', 'date_format:H:i'],

            'consent_given' => ['accepted'],

            ...$this->attributionRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('site.booking.full_name'),
            'email' => __('site.booking.email'),
            'phone' => __('site.booking.phone_optional'),
            'notes' => __('site.booking.notes'),
            'service_id' => __('site.booking.topic'),
            'date' => __('site.booking.date'),
            'time' => __('site.booking.time'),
            'consent_given' => __('site.booking.consent'),
        ];
    }
}
