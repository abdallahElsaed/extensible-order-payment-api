<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Payment\PaymentMethod;
use App\Exceptions\Payment\UnsupportedPaymentMethodException;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use Illuminate\Contracts\Container\Container;

final class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGatewayContract>>
     */
    private readonly array $gateways;

    /**
     * @param  array<string, class-string<PaymentGatewayContract>>|null  $gateways
     */
    public function __construct(
        private readonly Container $container,
        ?array $gateways = null,
    ) {
        $this->gateways = $gateways ?? self::conventionMap();
    }

    public function resolve(PaymentMethod $method): PaymentGatewayContract
    {
        $gateway = $this->gateways[$method->value] ?? null;

        if ($gateway === null || ! is_subclass_of($gateway, PaymentGatewayContract::class)) {
            throw new UnsupportedPaymentMethodException($method->value);
        }

        return $this->container->make($gateway);
    }

    /**
     * @return array<string, class-string<PaymentGatewayContract>>
     */
    private static function conventionMap(): array
    {
        $map = [];

        foreach (PaymentMethod::cases() as $method) {
            $gateway = $method->gatewayClass();

            if (is_subclass_of($gateway, PaymentGatewayContract::class)) {
                $map[$method->value] = $gateway;
            }
        }

        return $map;
    }
}
