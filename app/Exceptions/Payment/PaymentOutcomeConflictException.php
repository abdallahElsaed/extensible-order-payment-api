<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

class PaymentOutcomeConflictException extends Exception
{
    public function __construct(string $message = 'This payment was already settled differently and needs manual review.')
    {
        parent::__construct($message);
    }
}
