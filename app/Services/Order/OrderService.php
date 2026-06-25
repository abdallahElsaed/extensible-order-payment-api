<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\OrderItemData;
use App\DTOs\Order\UpdateOrderData;
use App\Enums\Order\OrderStatus;
use App\Exceptions\Order\InvalidStatusTransitionException;
use App\Exceptions\Order\OrderHasPaymentsException;
use App\Models\Order;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function paginateForUser(User $user, ?OrderStatus $status, int $perPage): LengthAwarePaginator
    {
        return $user->orders()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('items')
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(User $user, int $orderId): Order
    {
        return $user->orders()->with('items')->findOrFail($orderId);
    }

    public function create(User $user, CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $order = $user->orders()->create([
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'status' => OrderStatus::Pending,
                'total' => $this->sumItems($data->items)->minorUnits(),
            ]);

            $this->persistItems($order, $data->items);

            return $order->load('items');
        });
    }

    public function update(Order $order, UpdateOrderData $data): Order
    {
        if ($data->status !== null && ! $order->status->canTransitionTo($data->status)) {
            throw new InvalidStatusTransitionException($order->status, $data->status);
        }

        return DB::transaction(function () use ($order, $data): Order {
            if ($data->status !== null) {
                $order->status = $data->status;
            }

            if ($data->items !== null) {
                $order->items()->delete();
                $this->persistItems($order, $data->items);
                $order->total = $this->sumItems($data->items)->minorUnits();
            }

            $order->save();

            return $order->load('items');
        });
    }

    public function delete(Order $order): void
    {
        if ($order->payments()->exists()) {
            throw new OrderHasPaymentsException;
        }

        $order->delete();
    }

    /**
     * @param  array<int, OrderItemData>  $items
     */
    private function persistItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $order->items()->create([
                'product_name' => $item->productName,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice->minorUnits(),
                'line_total' => $item->lineTotal()->minorUnits(),
            ]);
        }
    }

    /**
     * @param  array<int, OrderItemData>  $items
     */
    private function sumItems(array $items): Money
    {
        return array_reduce(
            $items,
            fn (Money $carry, OrderItemData $item): Money => $carry->add($item->lineTotal()),
            Money::fromMinor(0),
        );
    }
}
