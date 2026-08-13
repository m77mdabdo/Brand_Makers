<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\HasAttributionFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    use HasAttributionFields;

    /**
     * An open public form — there is no authorisation gate to apply.
     * Abuse is handled by the route throttle and the honeypot, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // Permissive on purpose — international numbering varies too
            // much to pin down, and the value is only ever rendered as a
            // tel: link.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'message' => ['required', 'string', 'max:5000'],

            // `accepted`, not `boolean`: the box must actually be ticked.
            // Consent has to be provable per lead, which is why the
            // schema stores consented_at alongside it.
            'consent_given' => ['accepted'],
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
        ];
    }
}
