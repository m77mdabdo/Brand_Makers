<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /**
     * The route already carries ['auth', 'admin'], so authorisation here
     * is a deliberate pass-through rather than an omission.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTE WHAT IS MISSING: `reference`, `status`, `progress` and
     * `tracking_code_hash`.
     *
     * All four are issued by the system, not supplied. Their absence here
     * is one of three layers — Order::$fillable guards them, and
     * OrderService::fillable() intersects the payload with an explicit
     * list before any mass assignment.
     *
     * `note` is absent too, unlike the create request: editing a client's
     * details is not a timeline event, and silently appending one every
     * time somebody fixed a typo in a phone number would fill the client's
     * portal with noise.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],

            'package_id' => ['nullable', 'integer', Rule::exists('packages', 'id')->whereNull('deleted_at')],
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->whereNull('deleted_at')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],

            'total' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'placed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_name' => __('dashboard.orders.client_name'),
            'client_email' => __('dashboard.orders.client_email'),
            'client_phone' => __('dashboard.orders.client_phone'),
            'package_id' => __('dashboard.resources.packages'),
            'lead_id' => __('dashboard.orders.linked_lead'),
            'total' => __('dashboard.orders.total'),
            'placed_at' => __('dashboard.orders.placed_at'),
        ];
    }
}
