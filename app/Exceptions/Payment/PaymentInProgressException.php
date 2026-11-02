<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class PaymentInProgressException extends Exception
{
    public function __construct(string $message = 'A payment for this order is already in progress.')
    {
        parent::__construct($message);
    }
}
