<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
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
            'method' => PaymentMethod::CreditCard,
            'status' => PaymentStatus::Successful,
            'reference' => fake()->uuid(),
            'message' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Successful]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::Failed]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => ['method' => PaymentMethod::CreditCard]);
    }

    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => ['method' => PaymentMethod::Paypal]);
    }
}
