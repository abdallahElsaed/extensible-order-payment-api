<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class UnsupportedPaymentMethodException extends Exception
{
    public function __construct(public readonly string $method)
    {
        parent::__construct(sprintf('Unsupported payment method: %s.', $method));
    }
}
