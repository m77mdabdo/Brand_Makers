<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\HasSeoMetaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    use HasSeoMetaRules;

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
             * Uniqueness INCLUDING soft-deleted rows (Rule::unique's
             * default — it queries the table with no global scope). The DB
             * index counts trashed rows too, so skipping them here would
             * turn a field error into a 500 on insert.
             */
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                // ignore() the record being edited, so saving without
                // changing the slug is not a conflict with itself.
                Rule::unique('services', 'slug')->ignore($this->route('service')),
            ],
            'icon' => ['nullable', 'string', 'max:64'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'is_flagship' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],

            /*
             * NULLABLE, and that is a real answer: blank means "على
             * الطلب", the same fact packages and estimator items express
             * the same way. decimal(12,2) — the max is what the column
             * can hold.
             */
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],

            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'summary' => ['required', 'array'],
            'summary.ar' => ['required', 'string', 'max:1000'],
            'summary.en' => ['nullable', 'string', 'max:1000'],

            'body' => ['required', 'array'],
            'body.ar' => ['required', 'string', 'max:20000'],
            'body.en' => ['nullable', 'string', 'max:20000'],

            'requirements' => ['required', 'array'],
            'requirements.ar' => ['required', 'string', 'max:20000'],
            'requirements.en' => ['nullable', 'string', 'max:20000'],

            'process' => ['required', 'array'],
            'process.ar' => ['required', 'string', 'max:20000'],
            'process.en' => ['nullable', 'string', 'max:20000'],
        ] + $this->seoMetaRules();
    }
}
