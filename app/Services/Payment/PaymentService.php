<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\DTOs\Payment\GatewayResult;
use App\DTOs\Payment\IdempotencyData;
use App\DTOs\Payment\ProcessPaymentData;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\Payment\IdempotencyKeyReusedException;
use App\Exceptions\Payment\OrderAlreadyPaidException;
use App\Exceptions\Payment\OrderNotConfirmedException;
use App\Exceptions\Payment\PaymentInProgressException;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaymentService
{
    public function __construct(private readonly PaymentGatewayRegistry $registry) {}

    /**
     * The gateway call sits between two short database steps, so no row lock is
     * held while waiting on a third party. See docs/payment-flow.md.
     */
    public function process(Order $order, PaymentMethod $method, ?IdempotencyData $idempotency = null): Payment
    {
        try {
            $payment = DB::transaction(fn (): Payment => $this->reserve($order, $method, $idempotency));
        } catch (UniqueConstraintViolationException) {
            throw new IdempotencyKeyReusedException;
        }

        return $payment->wasRecentlyCreated ? $this->charge($payment) : $payment;
    }

    /**
     * Mark every payment left pending past its TTL as failed, freeing the order for a retry.
     */
    public function expireStale(): int
    {
        $expired = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes((int) config('payments.pending_ttl_minutes')))
            ->update([
                'status' => PaymentStatus::Failed,
                'message' => 'Expired: the gateway outcome was never confirmed.',
            ]);

        if ($expired > 0) {
            Log::warning('Expired stale pending payments.', ['count' => $expired]);
        }

        return $expired;
    }

    /**
     * The lock must stay the transaction's first statement: under REPEATABLE READ
     * an earlier plain read would pin a snapshot that hides a rival's commit.
     */
    private function reserve(Order $order, PaymentMethod $method, ?IdempotencyData $idempotency): Payment
    {
        $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        $replayed = $idempotency !== null ? $this->findReplayed($order, $idempotency) : null;

        if ($replayed !== null) {
            return $replayed;
        }

        if ($order->status !== OrderStatus::Confirmed) {
            throw new OrderNotConfirmedException;
        }

        if ($order->payments()->where('status', PaymentStatus::Successful)->exists()) {
            throw new OrderAlreadyPaidException;
        }

        if ($order->payments()->where('status', PaymentStatus::Pending)->exists()) {
            throw new PaymentInProgressException;
        }

        $this->registry->resolve($method);

        $payment = $order->payments()->create(['method' => $method, 'status' => PaymentStatus::Pending]);

        if ($idempotency !== null) {
            IdempotencyKey::query()->create([
                'user_id' => $order->user_id,
                'key' => $idempotency->key,
                'request_hash' => $idempotency->requestHash,
                'payment_id' => $payment->id,
            ]);
        }

        return $payment->setRelation('order', $order);
    }

    private function findReplayed(Order $order, IdempotencyData $idempotency): ?Payment
    {
        $record = IdempotencyKey::query()
            ->where('user_id', $order->user_id)
            ->where('key', $idempotency->key)
            ->first();

        if ($record === null) {
            return null;
        }

        if (! hash_equals($record->request_hash, $idempotency->requestHash)) {
            throw new IdempotencyKeyReusedException;
        }

        return $record->payment;
    }

    /**
     * Any exception means the outcome is unknown: a timeout may still have moved
     * money, so the payment stays pending instead of being reported as failed.
     */
    private function charge(Payment $payment): Payment
    {
        try {
            $result = $this->registry->resolve($payment->method)
                ->process(new ProcessPaymentData($payment->order, $payment->method));
        } catch (Throwable $exception) {
            report($exception);

            return $payment;
        }

        return $this->finalise($payment, $result);
    }

    /**
     * Conditional on pending, so a late gateway result cannot overwrite an expiry.
     */
    private function finalise(Payment $payment, GatewayResult $result): Payment
    {
        Payment::query()
            ->whereKey($payment->getKey())
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => $result->status,
                'reference' => $result->reference,
                'message' => $result->message,
            ]);

        $payment->refresh();

        if ($payment->status !== $result->status) {
            Log::critical('Gateway result arrived after the payment was settled; needs manual review.', [
                'payment' => $payment->uuid,
                'recorded_status' => $payment->status->value,
                'gateway_status' => $result->status->value,
                'gateway_reference' => $result->reference,
            ]);
        }

        return $payment;
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
