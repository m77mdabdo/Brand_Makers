<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

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

            // Nullable: blank means "على الطلب", not zero. See
            // StoreEstimatorItemRequest for the full reasoning.
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],

            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
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
