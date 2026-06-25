<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class CreateOrderData
{
    /**
     * @param  array<int, OrderItemData>  $items
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $items,
    ) {}

    /**
     * @param  array{customer_name: string, customer_email: string, items: array<int, array<string, mixed>>}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            customerName: $validated['customer_name'],
            customerEmail: $validated['customer_email'],
            items: array_map(
                fn (array $item): OrderItemData => OrderItemData::fromArray($item),
                $validated['items'],
            ),
        );
    }
}
