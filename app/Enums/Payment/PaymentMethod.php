<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Services\Payment\Contracts\PaymentGatewayContract;

enum PaymentMethod: string
{
    case CreditCard = 'credit_card';
    case Paypal = 'paypal';

    /**
     * @return class-string<PaymentGatewayContract>
     */
    public function gatewayClass(): string
    {
        return sprintf('App\\Services\\Payment\\Gateways\\%sGateway', $this->name);
    }
}
