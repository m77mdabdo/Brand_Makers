<?php

namespace App\Http\Requests\Dashboard;

use App\Models\EstimatorGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The route already carries ['auth', 'admin'], so authorisation here is a
 * deliberate pass-through rather than an omission.
 */
class UpdateEstimatorGroupRequest extends FormRequest
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
             * NO `key` RULE, deliberately. estimator_items.group points at
             * it by value, so renaming a key here would orphan every item
             * in the group from its heading in one write. validated()
             * therefore drops it and the service never sees it.
             */

            'label' => ['required', 'array'],
            'label.ar' => ['required', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],

            'selection' => ['required', Rule::in(EstimatorGroup::SELECTIONS)],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label.ar' => __('dashboard.estimator_groups.label_ar'),
            'label.en' => __('dashboard.estimator_groups.label_en'),
            'selection' => __('dashboard.estimator_groups.selection'),
            'sort_order' => __('dashboard.fields.sort_order'),
        ];
    }
}
