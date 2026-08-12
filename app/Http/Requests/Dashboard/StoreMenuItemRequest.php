<?php

namespace App\Http\Requests\Dashboard;

use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
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
            'label' => ['required', 'array'],
            'label.ar' => ['required', 'string', 'max:100'],
            'label.en' => ['nullable', 'string', 'max:100'],

            'link_type' => ['required', Rule::in(MenuItem::LINK_TYPES)],

            /*
             * THE ROUTE WHITELIST, enforced here as well as in the model.
             *
             * Only parameter-free public routes are allowed. A menu entry
             * pointing at a route that needs a bound model (services.show)
             * could not be resolved without a parameter, and route() would
             * throw — taking down the navbar, and therefore EVERY page on
             * the site, not just the one being edited.
             */
            'route_name' => [
                'nullable',
                'required_if:link_type,route',
                Rule::in(array_keys(MenuItem::ROUTE_WHITELIST)),
            ],

            // Relative paths and fragments are allowed as well as absolute
            // URLs — a fragment resolves against the current locale's
            // homepage (see MenuItem::href).
            'url' => ['nullable', 'required_if:link_type,url', 'string', 'max:2048'],

            'target' => ['nullable', Rule::in(MenuItem::TARGETS)],

            /*
             * Nesting is capped at ONE level: a parent must itself be
             * top-level. The navbar renders parent → children and nothing
             * deeper, so a grandchild would silently vanish from the site.
             */
            'parent_id' => [
                'nullable',
                Rule::exists('menu_items', 'id')->whereNull('parent_id'),
            ],

            'is_visible' => ['nullable', 'boolean'],
        ];
    }
}
