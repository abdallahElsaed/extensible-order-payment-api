<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethod;
use App\Exceptions\Payment\UnsupportedPaymentMethodException;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use App\Services\Payment\Gateways\CreditCardGateway;
use App\Services\Payment\Gateways\PaypalGateway;
use App\Services\Payment\PaymentGatewayRegistry;

it('resolves each method to its configured gateway', function () {
    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->resolve(PaymentMethod::CreditCard))->toBeInstanceOf(CreditCardGateway::class)
        ->and($registry->resolve(PaymentMethod::Paypal))->toBeInstanceOf(PaypalGateway::class);
});

it('resolves every payment method to a gateway', function (PaymentMethod $method) {
    expect(app(PaymentGatewayRegistry::class)->resolve($method))
        ->toBeInstanceOf(PaymentGatewayContract::class);
})->with(PaymentMethod::cases());

it('throws for a method without a registered gateway', function () {
    $registry = new PaymentGatewayRegistry(app(), []);

    $registry->resolve(PaymentMethod::CreditCard);
})->throws(UnsupportedPaymentMethodException::class);
