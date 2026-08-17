<?php

namespace App\Http\Requests\Dashboard;

use App\Models\EstimatorGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The route already carries ['auth', 'admin'], so authorisation here is a
 * deliberate pass-through rather than an omission.
 */
class StoreEstimatorGroupRequest extends FormRequest
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
             * Optional: the service slugs the Arabic label when this is
             * blank. alpha_dash because the value ends up in an element id
             * and in estimator_items.group, and unique because two groups
             * answering to one slug would make the join ambiguous.
             */
            'key' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('estimator_groups', 'key')],

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
            'key' => __('dashboard.fields.key'),
            'label.ar' => __('dashboard.estimator_groups.label_ar'),
            'label.en' => __('dashboard.estimator_groups.label_en'),
            'selection' => __('dashboard.estimator_groups.selection'),
            'sort_order' => __('dashboard.fields.sort_order'),
        ];
    }
}
