<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key_hash' => hash('sha256', fake()->uuid()),
            'request_hash' => hash('sha256', fake()->uuid()),
            'payment_id' => Payment::factory(),
        ];
    }
}
