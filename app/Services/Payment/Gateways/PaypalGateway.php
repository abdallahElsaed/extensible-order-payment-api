<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentStatus;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use Illuminate\Support\Str;

final class PaypalGateway implements PaymentGatewayContract
{
    public function process(ProcessPaymentData $data): GatewayResult
    {
        $outcome = PaymentStatus::from(
            (string) config('payments.simulation.paypal.outcome', PaymentStatus::Successful->value),
        );

        return new GatewayResult(
            status: $outcome,
            reference: 'pp_'.Str::uuid()->toString(),
            message: $outcome === PaymentStatus::Successful
                ? 'PayPal payment completed.'
                : 'PayPal payment failed.',
        );
    }
}
