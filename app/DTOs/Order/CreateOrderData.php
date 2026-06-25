<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class CreateOrderData
{
    /**
     * @param  array<int, OrderItemData>  $items
     */
    public function __construct(
        public array $items,
    ) {}

    /**
     * @param  array{items: array<int, array<string, mixed>>}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            items: array_map(
                fn (array $item): OrderItemData => OrderItemData::fromArray($item),
                $validated['items'],
            ),
        );
    }
}
