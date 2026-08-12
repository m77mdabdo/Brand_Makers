<?php

namespace App\Http\Requests\Dashboard;

use App\Models\TrackingSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackingSettingsRequest extends FormRequest
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
     * Rules are derived from TrackingSetting::FORMATS, so the accepted
     * keys and their shapes are stated once.
     *
     * WHY THE FORMATS ARE NOT COSMETIC. These values end up interpolated
     * into inline <script> blocks on every public page. Blade's escaping
     * does hold there, but that leaves the whole boundary resting on HTML
     * escaping behaving correctly inside a JavaScript context, which is
     * not what it is for. Pinning each ID to its platform's published
     * shape means nothing that is not an ID can reach the page at all.
     */
    public function rules(): array
    {
        $rules = [
            'active' => ['nullable', 'array'],
        ];

        foreach (TrackingSetting::FORMATS as $key => $pattern) {
            $rules[$key] = ['nullable', 'string', 'max:100', 'regex:'.$pattern];
            $rules['active.'.$key] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (array_keys(TrackingSetting::FORMATS) as $key) {
            $messages[$key.'.regex'] = __('dashboard.tracking.invalid_format', [
                'field' => __('dashboard.tracking.fields.'.$key),
            ]);
        }

        return $messages;
    }
}
