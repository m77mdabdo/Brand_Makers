<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTE WHAT IS MISSING: `status`.
     *
     * An event records what was true when it was written, and
     * OrderService::addEvent() takes it from the ORDER. Letting a payload
     * choose it would allow a timeline that contradicts the order it
     * belongs to — a client reading "delivered" on a line of an order that
     * is still in production.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Arabic-primary, matching every translatable field in this
            // project: a note with only English renders as a gap for an
            // Arabic-reading client.
            'note' => ['required', 'array'],
            'note.ar' => ['required', 'string', 'max:1000'],
            'note.en' => ['nullable', 'string', 'max:1000'],

            // Backdating is legitimate: a studio adding Tuesday's update on
            // Thursday should be able to say so. A FUTURE date is not — a
            // timeline entry that has not happened yet would show a client
            // an update dated next week.
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note.ar' => __('dashboard.orders.note').' ('.__('dashboard.common.arabic').')',
            'note.en' => __('dashboard.orders.note').' ('.__('dashboard.common.english').')',
            'occurred_at' => __('dashboard.orders.occurred_at'),
        ];
    }
}
