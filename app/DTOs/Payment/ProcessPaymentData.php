<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

use App\Enums\Payment\PaymentMethod;
use App\Models\Order;

final readonly class ProcessPaymentData
{
    public function __construct(
        public Order $order,
        public PaymentMethod $method,
    ) {}
}
