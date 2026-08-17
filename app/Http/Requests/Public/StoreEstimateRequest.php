<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\Concerns\HasAttributionFields;
use App\Services\Estimator\EstimatorService;
use Illuminate\Foundation\Http\FormRequest;

class StoreEstimateRequest extends FormRequest
{
    use HasAttributionFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],

            /*
             * The selection.
             *
             * `items` is validated for SHAPE only — that it is an array of
             * short strings within a sane count. Membership is deliberately
             * NOT checked with an `exists` rule: EstimatorService drops
             * unknown and inactive keys itself, and a rule here would turn
             * "an item was withdrawn while you were filling in the form"
             * into a validation error the visitor cannot act on.
             *
             * No price arrives from the client at all. There is nothing to
             * validate because there is nothing to trust — the total is
             * recomputed from the catalogue on every submission.
             */
            'package' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array', 'max:'.EstimatorService::MAX_SELECTED_ITEMS],
            'items.*' => ['string', 'max:255'],

            /*
             * Service slugs, kept separate from item keys because they are
             * drawn from a different table and could collide — merging
             * them would make that collision silent. Same as above, these
             * are identifiers only: NO price is accepted from the client,
             * and the figure is re-read from `services` on every
             * submission.
             */
            'services' => ['nullable', 'array', 'max:'.EstimatorService::MAX_SELECTED_ITEMS],
            'services.*' => ['string', 'max:255'],

            'consent_given' => ['accepted'],

            ...$this->attributionRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('site.estimator.full_name'),
            'email' => __('site.estimator.email'),
            'phone' => __('site.estimator.phone_optional'),
            'message' => __('site.estimator.message'),
            'consent_given' => __('site.estimator.consent'),
        ];
    }
}
