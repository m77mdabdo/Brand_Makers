<?php

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Concerns\HasSeoMetaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
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

    public function rules(): array
    {
        return [
            /*
             * Uniqueness INCLUDING soft-deleted rows (Rule::unique's
             * default — it queries the table with no global scope). The DB
             * index counts trashed rows too, so skipping them here would
             * turn a field error into a 500 on insert. HasSlug makes the
             * same choice when it auto-generates one.
             */
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('articles', 'slug')->ignore($this->route('article'))],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'is_published' => ['nullable', 'boolean'],
            /*
             * A DATE, not a boolean pair. Blank while publishing means
             * "now"; a future value schedules the post, which the public
             * Article::live scope enforces by filtering on
             * published_at <= now() — so scheduling needs no cron job.
             */
            'published_at' => ['nullable', 'date'],

            'title' => ['required', 'array'],
            'title.ar' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],

            'excerpt' => ['nullable', 'array'],
            'excerpt.ar' => ['nullable', 'string', 'max:500'],
            'excerpt.en' => ['nullable', 'string', 'max:500'],

            'body' => ['required', 'array'],
            'body.ar' => ['required', 'string', 'max:50000'],
            'body.en' => ['nullable', 'string', 'max:50000'],
        ] + $this->seoMetaRules();
    }
}
