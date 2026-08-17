<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\HasSeoMetaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
             * Uniqueness INCLUDING soft-deleted rows, which is what
             * Rule::unique does by default — it queries the table
             * directly, with no global scope applied.
             *
             * That is the behaviour we want here and it is worth stating,
             * because the instinct is to add withoutTrashed(): the DB
             * unique index on `slug` also counts trashed rows, so a
             * validator that skipped them would pass and the insert would
             * then die with a UniqueConstraintViolationException — a 500
             * where the admin should have seen a field error. HasSlug
             * makes the same choice when it auto-generates a slug.
             */
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('services', 'slug')],
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
