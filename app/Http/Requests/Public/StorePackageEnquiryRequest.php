<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\HasAttributionFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageEnquiryRequest extends FormRequest
{
    use HasAttributionFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'message' => ['nullable', 'string', 'max:5000'],
            'consent_given' => ['accepted'],

            /*
             * The chosen package, validated against what is actually on
             * sale — `active` as well as `exists`, so a crafted payload
             * cannot attach an enquiry to a package that has been
             * withdrawn from the public page.
             */
            'package_id' => [
                'required',
                Rule::exists('packages', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
        ] + $this->attributionRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('site.forms.name'),
            'email' => __('site.forms.email'),
            'phone' => __('site.forms.phone'),
            'message' => __('site.forms.message'),
            'consent_given' => __('site.forms.consent'),
            'package_id' => __('site.packages.label'),
        ];
    }
}
