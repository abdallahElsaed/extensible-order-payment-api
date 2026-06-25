<?php

declare(strict_types=1);

namespace App\Services\Payment\Contracts;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;

interface PaymentGatewayContract
{
    public function process(ProcessPaymentData $data): GatewayResult;
}
