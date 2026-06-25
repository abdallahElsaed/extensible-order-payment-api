<?php

declare(strict_types=1);

use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Services\Payment\Gateways\PaypalGateway;

function paypalContext(): ProcessPaymentData
{
    return new ProcessPaymentData(new Order, PaymentMethod::Paypal);
}

it('returns a successful result when configured to succeed', function () {
    config(['payments.simulation.paypal.outcome' => 'successful']);

    $result = (new PaypalGateway)->process(paypalContext());

    expect($result->status)->toBe(PaymentStatus::Successful)
        ->and($result->reference)->toStartWith('pp_')
        ->and($result->message)->not->toBeNull();
});

it('returns a failed result when configured to fail', function () {
    config(['payments.simulation.paypal.outcome' => 'failed']);

    $result = (new PaypalGateway)->process(paypalContext());

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($result->reference)->toStartWith('pp_');
});
