<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\ValueObjects\Money;

final readonly class OrderItemData
{
    public function __construct(
        public string $productName,
        public int $quantity,
        public Money $unitPrice,
    ) {}

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    /**
     * @param  array{product_name: string, quantity: int|string, unit_price: int|float|string}  $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            productName: $item['product_name'],
            quantity: (int) $item['quantity'],
            unitPrice: Money::fromDecimal($item['unit_price']),
        );
    }
}
