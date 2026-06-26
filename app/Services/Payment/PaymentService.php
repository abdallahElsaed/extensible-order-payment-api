<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\Payment\OrderAlreadyPaidException;
use App\Exceptions\Payment\OrderNotConfirmedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(private readonly PaymentGatewayRegistry $registry) {}

    public function process(Order $order, PaymentMethod $method): Payment
    {
        return DB::transaction(function () use ($order, $method): Payment {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Confirmed) {
                throw new OrderNotConfirmedException;
            }

            if ($order->payments()->where('status', PaymentStatus::Successful)->exists()) {
                throw new OrderAlreadyPaidException;
            }

            $gateway = $this->registry->resolve($method);

            $result = $gateway->process(new ProcessPaymentData($order, $method));

            return $order->payments()->create([
                'method' => $method,
                'status' => $result->status,
                'reference' => $result->reference,
                'message' => $result->message,
            ]);
        });
    }

    public function paginateForOrder(Order $order, int $perPage): LengthAwarePaginator
    {
        return $order->payments()->latest()->paginate($perPage);
    }

    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $uuid): Payment
    {
        return Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id))
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
