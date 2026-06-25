<?php

declare(strict_types=1);

namespace App\Enums\Payment;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
}
