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

            /*
             * The number the admin typed, and the whole point of the form.
             *
             * It was missing from these rules while the form posted it and
             * the service ignored it, so the reorder screen saved
             * successfully and changed nothing. Validated but NOT required
             * — same reasoning as parent_id above: a cleared field is
             * normalised by the service rather than rejected, because
             * losing an entire arrangement over one blank box is a worse
             * outcome than treating that box as zero.
             */
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
