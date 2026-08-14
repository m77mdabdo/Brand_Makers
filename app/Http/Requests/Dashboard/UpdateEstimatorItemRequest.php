<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEstimatorItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTE WHAT IS MISSING: `key`.
     *
     * It is set once, on create, and is immutable afterwards — the seeder
     * matches on it to stay idempotent, and every stored estimate snapshot
     * records it so a quoted line can be traced back to its catalogue row.
     * Its absence here is the enforcement: validated() drops it, so
     * EstimatorItemService never sees one on an update and a payload that
     * smuggles one in changes nothing.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'array'],
            'label.ar' => ['required', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],

            'group' => ['required', 'string', 'max:255', 'alpha_dash'],

            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],

            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Identical rule to the create request, and for the same reason: an
     * item cannot be switched on until it has a real price, so nothing
     * that adds 0 SAR to a visitor's quote can reach the public estimator.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_active') && (float) $this->input('price') <= 0) {
                $validator->errors()->add('price', __('dashboard.estimator_items.active_needs_price'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label.ar' => __('dashboard.estimator_items.label_field').' ('.__('dashboard.common.arabic').')',
            'label.en' => __('dashboard.estimator_items.label_field').' ('.__('dashboard.common.english').')',
            'group' => __('dashboard.estimator_items.group'),
            'price' => __('dashboard.estimator_items.price'),
            'sort_order' => __('dashboard.fields.sort_order'),
        ];
    }
}
