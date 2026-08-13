<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\HasAttributionFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    use HasAttributionFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],

            /*
             * NOTE what is absent: `status`.
             *
             * It is not a rule here, it is not in Comment's fillable
             * path from this controller, and the controller sets it
             * explicitly — three independent reasons a public submission
             * cannot arrive pre-approved.
             */
        ] + $this->attributionRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('site.forms.name'),
            'email' => __('site.forms.email'),
            'body' => __('site.forms.comment'),
        ];
    }
}
