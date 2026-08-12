<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Cms\MediaLibraryService;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
            /*
             * `image` validates the file's actual CONTENT, not the
             * Content-Type header the client claimed — a .png header on a
             * PHP file does not pass. `mimes` restates the accepted list
             * visibly rather than inheriting a framework default.
             *
             * No SVG: it is an executable document, storage/app/public is
             * served same-origin, and there is no SVG sanitiser here.
             */
            'file' => [
                'required', 'file', 'image',
                'mimes:jpeg,jpg,png,webp,gif,avif',
                'max:'.MediaLibraryService::MAX_KILOBYTES,
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
