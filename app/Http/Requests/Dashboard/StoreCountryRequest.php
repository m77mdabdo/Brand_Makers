<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\HasSeoMetaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
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
             * turn a field error into a 500 on insert. HasSlug makes the
             * same choice when it auto-generates one.
             */
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('countries', 'slug')],
            'flag_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'array'],
            'notes.ar' => ['nullable', 'string', 'max:5000'],
            'notes.en' => ['nullable', 'string', 'max:5000'],
        ] + $this->seoMetaRules();
    }
}
