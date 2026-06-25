<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentStatus;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use Illuminate\Support\Str;

final class CreditCardGateway implements PaymentGatewayContract
{
    public function process(ProcessPaymentData $data): GatewayResult
    {
        $outcome = PaymentStatus::from(
            (string) config('payments.simulation.credit_card.outcome', PaymentStatus::Successful->value),
        );

        return new GatewayResult(
            status: $outcome,
            reference: 'cc_'.Str::uuid()->toString(),
            message: $outcome === PaymentStatus::Successful
                ? 'Credit card payment approved.'
                : 'Credit card payment declined.',
        );
    }
}
