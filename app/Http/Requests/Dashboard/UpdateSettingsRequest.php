<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Cms\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
     * Rules are DERIVED from SettingsService::DEFINITION.
     *
     * A key added to the definition is validated automatically, and a key
     * that is not in it has no rule at all — so validated() drops it. That
     * is the first half of the arbitrary-key protection; the second is
     * that the service iterates the definition rather than the payload.
     */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array']];

        foreach (SettingsService::keys() as $key => $type) {
            $field = "settings.{$key}";

            $rules += match ($type) {
                SettingsService::TYPE_TRANSLATED => [
                    $field => ['nullable', 'array'],
                    // Arabic-primary, matching every translatable model
                    // field in this project: English may be left blank and
                    // falls back, but a lone English value with no Arabic
                    // would render as a gap on the default locale.
                    "{$field}.ar" => ['nullable', 'required_with:'.$field.'.en', 'string', 'max:255'],
                    "{$field}.en" => ['nullable', 'string', 'max:255'],
                ],
                SettingsService::TYPE_TRANSLATED_AREA => [
                    $field => ['nullable', 'array'],
                    "{$field}.ar" => ['nullable', 'required_with:'.$field.'.en', 'string', 'max:500'],
                    "{$field}.en" => ['nullable', 'string', 'max:500'],
                ],
                SettingsService::TYPE_EMAIL => [$field => ['nullable', 'string', 'email', 'max:255']],
                // Permissive on purpose — international numbering varies
                // too much to pin down, and the value is only ever
                // rendered as a tel: link.
                SettingsService::TYPE_PHONE => [$field => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{6,30}$/']],
                SettingsService::TYPE_URL => [$field => ['nullable', 'string', 'url', 'max:255', 'starts_with:https://,http://']],
                SettingsService::TYPE_BOOLEAN => [$field => ['nullable', 'boolean']],
                SettingsService::TYPE_INTEGER => [$field => ['nullable', 'integer', 'min:1', 'max:3650']],
                SettingsService::TYPE_TEXTAREA => [$field => ['nullable', 'string', 'max:500']],
                // The logo arrives as a file, outside the settings array.
                SettingsService::TYPE_IMAGE => [],
                default => [$field => ['nullable', 'string', 'max:255']],
            };
        }

        /*
         * NO SVG, deliberately.
         *
         * An SVG is an executable document. storage/settings/* is served
         * from this origin, so navigating straight to an uploaded SVG
         * would run any script it carries as same-origin code. There is no
         * SVG sanitiser in this project, so the format is simply not
         * accepted.
         *
         * `mimes` is stated alongside `image` so the accepted list is
         * visible here rather than inherited from a framework default.
         */
        $rules['site_logo'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (array_keys(SettingsService::keys()) as $key) {
            $label = __('dashboard.settings.fields.'.$key);

            $attributes["settings.{$key}"] = $label;
            $attributes["settings.{$key}.ar"] = $label.' ('.__('dashboard.common.arabic').')';
            $attributes["settings.{$key}.en"] = $label.' ('.__('dashboard.common.english').')';
        }

        return $attributes;
    }
}
