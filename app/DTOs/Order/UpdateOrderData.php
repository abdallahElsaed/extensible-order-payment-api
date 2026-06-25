<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\Enums\Order\OrderStatus;

final readonly class UpdateOrderData
{
    /**
     * @param  array<int, OrderItemData>|null  $items
     */
    public function __construct(
        public ?string $customerName,
        public ?string $customerEmail,
        public ?OrderStatus $status,
        public ?array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            customerName: $validated['customer_name'] ?? null,
            customerEmail: $validated['customer_email'] ?? null,
            status: isset($validated['status']) ? OrderStatus::from($validated['status']) : null,
            items: isset($validated['items'])
                ? array_map(
                    fn (array $item): OrderItemData => OrderItemData::fromArray($item),
                    $validated['items'],
                )
                : null,
        );
    }
}
