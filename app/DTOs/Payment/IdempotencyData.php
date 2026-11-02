<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

final readonly class IdempotencyData
{
    public function __construct(
        public string $key,
        public string $requestHash,
    ) {}
}
