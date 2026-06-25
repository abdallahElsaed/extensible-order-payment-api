<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'method' => 'credit_card',
            'status' => 'successful',
            'reference' => fake()->uuid(),
            'message' => null,
        ];
    }
}
