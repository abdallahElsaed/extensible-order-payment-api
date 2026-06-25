<?php

declare(strict_types=1);

namespace App\Enums\Order;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Pending, self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Confirmed, self::Cancelled],
            self::Cancelled => [self::Cancelled],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
