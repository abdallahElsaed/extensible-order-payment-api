<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentStatus;
use App\Services\Payment\Contracts\PaymentGatewayContract;

/**
 * A brand-new gateway that lives entirely outside app/. It exists only to prove
 * a gateway can be added by implementing the contract, with zero edits to any
 * shipped gateway or to the core payment flow (SC-006).
 */
final class StripeGateway implements PaymentGatewayContract
{
    public const REFERENCE = 'stripe_fixture_ref';

    public function process(ProcessPaymentData $data): GatewayResult
    {
        return new GatewayResult(
            status: PaymentStatus::Successful,
            reference: self::REFERENCE,
            message: 'Processed by the fixture Stripe gateway.',
        );
    }
}
