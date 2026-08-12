<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => Order::STATUS_PENDING,
            'note' => ['ar' => 'تم استلام الطلب', 'en' => 'Order received'],
            // NOT NULL with no portable default — never omit it.
            'occurred_at' => now()->subDay(),
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
