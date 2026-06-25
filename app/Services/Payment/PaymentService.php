<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentMethod;
use App\Exceptions\Payment\OrderNotConfirmedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaymentService
{
    public function __construct(private readonly PaymentGatewayRegistry $registry) {}

    public function process(Order $order, PaymentMethod $method): Payment
    {
        if ($order->status !== OrderStatus::Confirmed) {
            throw new OrderNotConfirmedException;
        }

        $gateway = $this->registry->resolve($method);

        $result = $gateway->process(new ProcessPaymentData($order, $method));

        return $order->payments()->create([
            'method' => $method,
            'status' => $result->status,
            'reference' => $result->reference,
            'message' => $result->message,
        ]);
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
