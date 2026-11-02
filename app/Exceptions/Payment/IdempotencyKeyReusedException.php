<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class IdempotencyKeyReusedException extends Exception
{
    public function __construct(string $message = 'This Idempotency-Key was already used with a different request.')
    {
        parent::__construct($message);
    }
}
