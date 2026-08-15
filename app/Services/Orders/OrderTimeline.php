<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderEvent;

/**
 * The client-facing view of where an order has got to.
 *
 * TWO DIFFERENT THINGS ARE BEING SHOWN, and conflating them is the mistake
 * this class exists to avoid:
 *
 *   THE STAGES are a fixed, ordered map of the journey — received, in
 *   production, in review, delivered. They are always all four, so the
 *   client can see what is still ahead of them rather than only what has
 *   happened. Each is marked done, current or upcoming.
 *
 *   THE EVENTS are what the studio actually wrote, in the order it wrote
 *   them, including corrections. A rollback appears here as its own line
 *   rather than by the history rearranging itself.
 *
 * `cancelled` is deliberately NOT a stage. It is not a step on the way to
 * anywhere, and rendering it as the fifth box would imply every order
 * passes through it. A cancelled order shows the stages it reached, with
 * the cancellation on the event list where it belongs.
 */
class OrderTimeline
{
    /**
     * The journey, in order. The keys are Order statuses; the labels come
     * from the site language files so the client reads studio language
     * ("in production") rather than the schema's.
     *
     * @var list<string>
     */
    public const STAGES = [
        Order::STATUS_PENDING,
        Order::STATUS_IN_PROGRESS,
        Order::STATUS_IN_REVIEW,
        Order::STATUS_COMPLETED,
    ];

    /**
     * @return list<array{status: string, state: string, position: int}>
     */
    public function stages(Order $order): array
    {
        $reached = $this->reachedIndex($order);

        $stages = [];

        foreach (self::STAGES as $position => $status) {
            $stages[] = [
                'status' => $status,
                'state' => match (true) {
                    // A cancelled order has no CURRENT stage — it stopped.
                    // Marking one "current" would suggest work in flight.
                    $order->status === Order::STATUS_CANCELLED => $position <= $reached ? 'done' : 'upcoming',
                    $position < $reached => 'done',
                    $position === $reached => 'current',
                    default => 'upcoming',
                },
                'position' => $position,
            ];
        }

        return $stages;
    }

    /**
     * Stages plus the written history, which is what the portal renders.
     *
     * @return array{stages: list<array{status: string, state: string, position: int}>, events: list<OrderEvent>, cancelled: bool}
     */
    public function forOrder(Order $order): array
    {
        return [
            'stages' => $this->stages($order),
            // Oldest first — the relation orders by occurred_at then id,
            // so two entries written in the same second still read in the
            // order they were added.
            'events' => $order->events->all(),
            'cancelled' => $order->status === Order::STATUS_CANCELLED,
        ];
    }

    /**
     * How far along the stage list this order has got.
     *
     * A cancelled order keeps the furthest stage it reached rather than
     * resetting: "we cancelled while in review" is more use to a client
     * than "cancelled", and the order's own progress figure is what
     * records it. Falls back to the first stage for a status that is not
     * on the list at all, so an extended vocabulary degrades to "received"
     * rather than to an out-of-range index.
     */
    private function reachedIndex(Order $order): int
    {
        if ($order->status === Order::STATUS_CANCELLED) {
            return $this->indexFromProgress($order);
        }

        $index = array_search($order->status, self::STAGES, true);

        return $index === false ? 0 : $index;
    }

    /**
     * For a cancelled order, the stage its progress figure implies.
     *
     * Coarse on purpose: this is the one place a stage is inferred rather
     * than recorded, and pretending to more precision than 0/33/66/100
     * would be inventing detail.
     */
    private function indexFromProgress(Order $order): int
    {
        return match (true) {
            $order->progress >= 100 => 3,
            $order->progress >= 66 => 2,
            $order->progress >= 33 => 1,
            default => 0,
        };
    }
}
