<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'status' => OrderStatus::Pending,
            'total' => fake()->numberBetween(0, 100000),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Pending]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrderStatus::Cancelled]);
    }
}
