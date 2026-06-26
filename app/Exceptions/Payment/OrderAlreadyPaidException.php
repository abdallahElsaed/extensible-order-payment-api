<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class OrderAlreadyPaidException extends Exception
{
    public function __construct(string $message = 'This order has already been paid.')
    {
        parent::__construct($message);
    }
}
