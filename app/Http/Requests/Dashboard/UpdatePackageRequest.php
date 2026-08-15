<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\HasSeoMetaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Uniqueness INCLUDING soft-deleted rows, which is what
             * Rule::unique does by default. The DB unique index on `slug`
             * counts trashed rows too, so a validator that skipped them
             * would pass and the insert would then die with a
             * UniqueConstraintViolationException — a 500 where the admin
             * should have seen a field error. HasSlug makes the same
             * choice when it generates one.
             *
             * ->ignore() the row being edited, or saving a package without
             * touching its slug would report the slug as already taken by
             * itself.
             */
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('packages', 'slug')->ignore($this->route('package'))],

            'name' => ['required', 'array'],
            // Arabic-primary, matching every translatable field in this
            // project: English may be blank and falls back, but a package
            // with only English renders as a gap on the default locale.
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:1000'],
            'description.en' => ['nullable', 'string', 'max:1000'],

            /*
             * NULLABLE, and that is the point: null means "on request",
             * which is a different fact from 0.00. The public card renders
             * the two differently (Package::isPriceOnRequest), so a
             * required rule here would force every package to carry a
             * number the studio may not have agreed.
             */
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],

            // One feature per line; line N in Arabic pairs with line N in
            // English. See PackageContentService::featureList().
            'features' => ['nullable', 'array'],
            'features.ar' => ['nullable', 'string', 'max:4000'],
            'features.en' => ['nullable', 'string', 'max:4000'],

            'is_popular' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ] + $this->seoMetaRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.ar' => __('dashboard.fields.name').' ('.__('dashboard.common.arabic').')',
            'name.en' => __('dashboard.fields.name').' ('.__('dashboard.common.english').')',
            'price' => __('dashboard.packages.price'),
            'features.ar' => __('dashboard.packages.features').' ('.__('dashboard.common.arabic').')',
            'features.en' => __('dashboard.packages.features').' ('.__('dashboard.common.english').')',
        ];
    }
}
