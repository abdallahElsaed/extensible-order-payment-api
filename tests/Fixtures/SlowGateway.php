<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentStatus;
use App\Services\Payment\Contracts\PaymentGatewayContract;

/**
 * Stays inside the gateway long enough for racing requests to overlap.
 */
final class SlowGateway implements PaymentGatewayContract
{
    public function process(ProcessPaymentData $data): GatewayResult
    {
        usleep(400_000);

        return new GatewayResult(PaymentStatus::Successful, 'slow_ref', 'Processed by the slow gateway.');
    }
}
