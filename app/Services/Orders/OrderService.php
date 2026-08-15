<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\Cms\HtmlSanitizerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an order.
 *
 * `reference`, `status`, `progress` and `tracking_code_hash` are all
 * guarded out of Order::$fillable, so no payload can set them; this class
 * is where they move, and where the rules about which move is legal live.
 *
 * THE STATUS GRAPH IS BIDIRECTIONAL, unlike the booking one.
 *
 *   pending     ↔ in_progress
 *   in_progress ↔ in_review
 *   in_review   → completed
 *   completed   → in_review          (rollback)
 *   cancelled   → pending            (reinstate)
 *   any live    → cancelled
 *
 * A booking's terminal states are final because reopening one would
 * silently re-take a slot the capacity check has since given away. An
 * order has no such shared resource: marking one delivered a day early is
 * an ordinary mistake, and refusing to correct it would leave the client
 * looking at a portal that says their work is done when it is not. So
 * rollback is allowed — and, because every transition writes a timeline
 * entry, it is allowed VISIBLY. The client sees the correction rather than
 * the history quietly changing.
 */
class OrderService
{
    /**
     * status => the statuses it may move to.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        Order::STATUS_PENDING => [Order::STATUS_IN_PROGRESS, Order::STATUS_CANCELLED],
        Order::STATUS_IN_PROGRESS => [Order::STATUS_PENDING, Order::STATUS_IN_REVIEW, Order::STATUS_CANCELLED],
        Order::STATUS_IN_REVIEW => [Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        Order::STATUS_COMPLETED => [Order::STATUS_IN_REVIEW],
        Order::STATUS_CANCELLED => [Order::STATUS_PENDING],
    ];

    public function __construct(private readonly HtmlSanitizerService $sanitizer) {}

    /**
     * @return list<string>
     */
    public static function allowedFor(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    /**
     * Creates an order and returns it with its plaintext tracking code
     * still attached.
     *
     * THE CODE IS READABLE EXACTLY ONCE, from the returned object, before
     * it goes out of scope. Only the digest is stored, so the admin screen
     * showing it is the only chance anyone has to write it down — which is
     * the point, and which the create screen says in as many words.
     *
     * The opening timeline entry is written in the same transaction: an
     * order whose portal shows an empty timeline reads as broken, and the
     * client's first question would be whether anything was received.
     *
     * @param  array<string, mixed>  $data  validated
     * @param  array<string, string>|null  $note  per-locale opening note
     */
    public function create(array $data, ?array $note = null): Order
    {
        return DB::transaction(function () use ($data, $note) {
            $order = Order::create([
                ...$this->fillable($data),
                'placed_at' => $this->placedAt($data),
            ]);

            $order->events()->create([
                'status' => $order->status,
                'note' => $this->sanitizer->sanitizeTranslations($note, false),
                'occurred_at' => $order->placed_at,
            ]);

            return $order;
        });
    }

    /**
     * Details only — never status, progress, reference or the code.
     *
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Order $order, array $data): Order
    {
        $order->update($this->fillable($data));

        return $order->refresh();
    }

    /**
     * Moves the order, writing the timeline entry in the same step.
     *
     * False rather than an exception on a refused move: the only caller is
     * a dashboard action, and an admin clicking a stale button deserves a
     * message rather than a 500.
     *
     * @param  array<string, string>|null  $note  per-locale
     */
    public function transition(Order $order, string $to, ?array $note = null, ?int $progress = null): bool
    {
        if (! in_array($to, self::allowedFor($order), true)) {
            return false;
        }

        return DB::transaction(fn () => $order->transitionTo(
            $to,
            $this->sanitizer->sanitizeTranslations($note, false),
            $progress,
        ));
    }

    /**
     * Progress without a status change — the ordinary "we are 60% through
     * production" update.
     *
     * Clamped in Order::setProgress() rather than validated-and-rejected:
     * the column is an unsignedTinyInteger, and an out-of-range value has
     * an obvious sane interpretation.
     */
    public function setProgress(Order $order, int $percent): Order
    {
        $order->setProgress($percent);
        $order->save();

        return $order;
    }

    /**
     * Adds a line to the timeline without moving the order.
     *
     * `status` is taken from the ORDER, not from the request: an event
     * records what was true when it was written, and letting a payload
     * choose it would allow a timeline that contradicts the order it
     * belongs to.
     *
     * @param  array<string, string>|null  $note  per-locale
     */
    public function addEvent(Order $order, ?array $note, ?string $occurredAt = null): OrderEvent
    {
        return $order->events()->create([
            'status' => $order->status,
            'note' => $this->sanitizer->sanitizeTranslations($note, false),
            'occurred_at' => $this->occurredAt($occurredAt),
        ]);
    }

    /**
     * @param  array<string, string>|null  $note  per-locale
     */
    public function updateEvent(OrderEvent $event, ?array $note, ?string $occurredAt = null): OrderEvent
    {
        $event->update([
            'note' => $this->sanitizer->sanitizeTranslations($note, false),
            'occurred_at' => $this->occurredAt($occurredAt, $event->occurred_at),
        ]);

        return $event->refresh();
    }

    public function deleteEvent(OrderEvent $event): void
    {
        $event->delete();
    }

    /**
     * Re-mints the tracking code, invalidating the old one.
     *
     * The way a leaked portal link is revoked: the previous code stops
     * working the moment this saves, and the new plaintext is readable
     * once from the returned string.
     */
    public function reissueTrackingCode(Order $order): string
    {
        $code = $order->issueTrackingCode();
        $order->save();

        return $code;
    }

    /**
     * The subset of a payload this service will pass to a mass assignment.
     *
     * Order::$fillable already excludes the four guarded columns, so this
     * is a second independent layer — the same arrangement the estimator
     * and settings writers use. A key that survived validation is still
     * never read.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillable(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'package_id', 'lead_id', 'user_id',
            'client_name', 'client_email', 'client_phone',
            'total', 'currency', 'notes',
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placedAt(array $data): CarbonImmutable
    {
        $value = $data['placed_at'] ?? null;

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : CarbonImmutable::now();
    }

    private function occurredAt(?string $value, mixed $fallback = null): CarbonImmutable
    {
        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value);
        }

        return $fallback !== null ? CarbonImmutable::parse($fallback) : CarbonImmutable::now();
    }
}
