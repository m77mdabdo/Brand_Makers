<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuRequest extends FormRequest
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
            'items' => ['required', 'array', 'max:200'],
            'items.*.id' => ['required', 'integer', 'exists:menu_items,id'],
            // Not validated against "must be top level" here: MenuService
            // normalises an illegal parent to null rather than rejecting
            // the whole reorder, so one bad row cannot lose the admin's
            // entire arrangement.
            'items.*.parent_id' => ['nullable'],
        ];
    }
}
