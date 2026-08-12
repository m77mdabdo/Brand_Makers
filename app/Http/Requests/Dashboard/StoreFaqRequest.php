<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
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
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'question' => ['required', 'array'],
            'question.ar' => ['required', 'string', 'max:500'],
            'question.en' => ['nullable', 'string', 'max:500'],

            'answer' => ['required', 'array'],
            'answer.ar' => ['required', 'string', 'max:5000'],
            'answer.en' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
