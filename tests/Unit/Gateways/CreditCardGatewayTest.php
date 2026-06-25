<?php

declare(strict_types=1);

use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\Gateways\CreditCardGateway;

function creditCardContext(): ProcessPaymentData
{
    return new ProcessPaymentData(new Order, PaymentMethod::CreditCard);
}

it('returns a successful result when configured to succeed', function () {
    config(['payments.simulation.credit_card.outcome' => 'successful']);

    $result = (new CreditCardGateway)->process(creditCardContext());

    expect($result->status)->toBe(PaymentStatus::Successful)
        ->and($result->reference)->toStartWith('cc_')
        ->and($result->message)->not->toBeNull();
});

it('returns a failed result when configured to fail', function () {
    config(['payments.simulation.credit_card.outcome' => 'failed']);

    $result = (new CreditCardGateway)->process(creditCardContext());

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($result->reference)->toStartWith('cc_');
});
