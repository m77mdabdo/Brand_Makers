<?php

namespace App\Services\Cms;

use App\Models\Service;

class ServiceContentService extends ContentResourceService
{
    /**
     * Every column this resource may write, and nothing else.
     *
     * The estimator reads a service's price directly, so this list is
     * what stands between a crafted payload and the figure on a public
     * pricing page. `currency` is absent deliberately, exactly as it is
     * for estimator items: the catalogue is single-currency and the
     * column carries a default, so exposing it in the editor would offer
     * a way to create a line that cannot be summed with the others.
     *
     * @var list<string>
     */
    private const WRITABLE = [
        'slug', 'icon', 'cover_image', 'is_flagship', 'sort_order', 'is_active',
        'name', 'summary', 'body', 'requirements', 'process', 'price',
    ];

    protected function model(): string
    {
        return Service::class;
    }

    /**
     * body / requirements / process carry rich HTML; name and summary are
     * reduced to plain text even though they arrive in the same
     * submission — a heading is not a place for markup.
     */
    protected function translatableFields(): array
    {
        return [
            'name' => false,
            'summary' => false,
            'body' => true,
            'requirements' => true,
            'process' => true,
        ];
    }

    protected function imageField(): ?array
    {
        return ['cover_image', 'services'];
    }

    protected function booleanFields(): array
    {
        return ['is_active', 'is_flagship'];
    }

    /**
     * `price` is not in Service::$fillable — see the model for why — so
     * it is assigned explicitly after the mass assignment.
     */
    protected function guardedFields(): array
    {
        return ['price'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitize(array $data): array
    {
        $data = array_intersect_key(parent::sanitize($data), array_flip(self::WRITABLE));

        if (array_key_exists('price', $data)) {
            /*
             * A blank price is NULL, not 0.00 — "على الطلب" rather than
             * free. Coercing a cleared box to zero would turn "we have not
             * priced this" into "this costs nothing" on the services page
             * and in every estimate built from it.
             */
            $data['price'] = $data['price'] === null || $data['price'] === ''
                ? null
                : number_format((float) $data['price'], 2, '.', '');
        }

        return $data;
    }
}
