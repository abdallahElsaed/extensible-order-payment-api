<?php

declare(strict_types=1);

namespace App\Exceptions\Order;

use App\Enums\Order\OrderStatus;
use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(
            sprintf('Cannot transition order from %s to %s.', $from->value, $to->value),
        );
    }
}
