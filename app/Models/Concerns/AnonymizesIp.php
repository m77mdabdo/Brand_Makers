<?php

namespace App\Models\Concerns;

use App\Services\Analytics\IpAnonymizer;

/**
 * Routes every write to an IP column through IpAnonymizer, in the model
 * rather than at the call site.
 *
 * The point is that there is no code path — controller, service, seeder,
 * factory, tinker session — that can put a raw address into the database
 * by forgetting a step. A model using this trait cannot store one.
 */
trait AnonymizesIp
{
    /**
     * The IP columns this model anonymizes. Override to change.
     *
     * @return list<string>
     */
    protected function anonymizedIpAttributes(): array
    {
        return ['ip_address'];
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->anonymizedIpAttributes(), true)) {
            $value = app(IpAnonymizer::class)->anonymize(is_string($value) ? $value : null);
        }

        return parent::setAttribute($key, $value);
    }
}
