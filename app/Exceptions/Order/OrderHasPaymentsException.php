<?php

declare(strict_types=1);

namespace App\Exceptions\Order;

use Exception;

class OrderHasPaymentsException extends Exception
{
    public function __construct(string $message = 'Order has associated payments and cannot be deleted.')
    {
        parent::__construct($message);
    }
}
