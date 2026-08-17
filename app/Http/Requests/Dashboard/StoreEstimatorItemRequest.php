<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstimatorItemRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Nullable: blank means "derive it from the label", which
             * EstimatorItemService does. Unique either way — a generated
             * key gets a -2 suffix, a typed one is reported as taken
             * rather than silently renamed.
             */
            'key' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('estimator_items', 'key')],

            'label' => ['required', 'array'],
            // Arabic-primary, matching every translatable field in this
            // project: English may be left blank and falls back.
            'label.ar' => ['required', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],

            // A free-form slug rather than an enum: the groups are
            // presentational headings, and a studio adding "motion" should
            // not need a deployment. Unrecognised groups render their raw
            // slug as the heading — see the estimator view.
            'group' => ['required', 'string', 'max:255', 'alpha_dash'],

            /*
             * NULLABLE, and that is a real answer rather than a missing
             * one: a blank price means "على الطلب", the same fact packages
             * have always been able to express. The column became nullable
             * so an unpriced line could stay visible and selectable instead
             * of having to be switched off.
             *
             * decimal(12,2) — the max is what the column can actually hold.
             */
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
            'key' => __('dashboard.fields.key'),
            'label.ar' => __('dashboard.estimator_items.label_field').' ('.__('dashboard.common.arabic').')',
            'label.en' => __('dashboard.estimator_items.label_field').' ('.__('dashboard.common.english').')',
            'group' => __('dashboard.estimator_items.group'),
            'price' => __('dashboard.estimator_items.price'),
            'sort_order' => __('dashboard.fields.sort_order'),
        ];
    }
}
