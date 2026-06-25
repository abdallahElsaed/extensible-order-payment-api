<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

use App\Enums\Payment\PaymentStatus;

final readonly class GatewayResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}
}
