<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageSectionRequest extends FormRequest
{
    /**
     * The route already carries ['auth', 'admin'], so authorisation here
     * is a deliberate pass-through rather than an omission.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * (page_id, key) is unique in the schema — a page cannot carry
             * the same section key twice — so the rule is scoped to this
             * page rather than the whole table.
             */
            'key' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('page_sections', 'key')
                    ->where('page_id', $this->route('page')->getKey())->ignore($this->route('section')),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'icon' => ['nullable', 'string', 'max:64'],

            // Flat fields, repacked into the JSON blob by
            // PageSectionService — see that class for why.
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
