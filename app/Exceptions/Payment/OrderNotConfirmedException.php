<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class OrderNotConfirmedException extends Exception
{
    public function __construct(string $message = 'Payments can only be processed for confirmed orders.')
    {
        parent::__construct($message);
    }
}
