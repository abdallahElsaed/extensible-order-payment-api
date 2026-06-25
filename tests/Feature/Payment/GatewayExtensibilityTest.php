<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Gateways\CreditCardGateway;
use App\Services\Payment\Gateways\PaypalGateway;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Contracts\Foundation\Application;
use Tests\Fixtures\StripeGateway;

it('routes a payment to a newly added gateway without touching existing gateways or the payment flow', function () {
    $this->app->singleton(
        PaymentGatewayRegistry::class,
        fn (Application $app): PaymentGatewayRegistry => new PaymentGatewayRegistry($app, [
            PaymentMethod::Paypal->value => StripeGateway::class,
        ]),
    );

    $user = User::factory()->create();
    $order = Order::factory()->for($user)->confirmed()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'paypal']);

    $response->assertCreated()
        ->assertJson([
            'data' => [
                'method' => 'paypal',
                'status' => 'successful',
                'reference' => StripeGateway::REFERENCE,
            ],
        ]);

    expect(Payment::query()->where('order_id', $order->id)->value('reference'))
        ->toBe(StripeGateway::REFERENCE);
});

it('still resolves the shipped gateways through the convention registry', function () {
    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->resolve(PaymentMethod::CreditCard))->toBeInstanceOf(CreditCardGateway::class)
        ->and($registry->resolve(PaymentMethod::Paypal))->toBeInstanceOf(PaypalGateway::class);
});
