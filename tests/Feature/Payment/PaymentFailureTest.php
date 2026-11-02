<?php

declare(strict_types=1);

use App\DTOs\Payment\GatewayResult;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\CallbackGateway;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::factory()->for($this->user)->confirmed()->create();
    useCreditCardGateway(CallbackGateway::class);
});

afterEach(function () {
    CallbackGateway::$callback = null;
});

function pay(Order $order, User $user): TestResponse
{
    return test()->actingAs($user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);
}

it('commits a pending record before the gateway runs and calls it outside any transaction', function () {
    $levelBeforeRequest = DB::transactionLevel();
    $statusDuringCall = null;
    $levelDuringCall = null;

    CallbackGateway::$callback = function () use (&$statusDuringCall, &$levelDuringCall): void {
        $statusDuringCall = Payment::query()->where('order_id', $this->order->id)->value('status');
        $levelDuringCall = DB::transactionLevel();
    };

    pay($this->order, $this->user)->assertCreated();

    expect($statusDuringCall)->toBe(PaymentStatus::Pending)
        ->and($levelDuringCall)->toBe($levelBeforeRequest);
});

it('finalises the pending record with the gateway result instead of adding a second one', function () {
    pay($this->order, $this->user)->assertCreated();

    $payment = Payment::query()->where('order_id', $this->order->id)->sole();

    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->reference)->toBe('callback_ref');
});

it('keeps the payment pending and answers 202 when the gateway outcome is unknown', function (Throwable $failure) {
    Exceptions::fake();
    CallbackGateway::$callback = fn () => throw $failure;

    $response = pay($this->order, $this->user);

    $response->assertStatus(202)->assertJson(['data' => ['status' => 'pending', 'reference' => null]]);
    expect(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Pending);
    Exceptions::assertReported($failure::class);
})->with([
    'timeout' => fn () => new ConnectionException('cURL error 28: Operation timed out'),
    'exception' => fn () => new RuntimeException('Gateway exploded'),
    'error' => fn () => new TypeError('Gateway bug'),
]);

it('stays pending when the gateway itself answers pending', function () {
    CallbackGateway::$callback = fn () => new GatewayResult(PaymentStatus::Pending, 'async_ref', 'Awaiting confirmation.');

    pay($this->order, $this->user)
        ->assertStatus(202)
        ->assertJson(['data' => ['status' => 'pending', 'reference' => 'async_ref']]);
});

it('rejects a new attempt with 409 while another payment is pending', function () {
    Payment::factory()->for($this->order)->pending()->create();

    pay($this->order, $this->user)->assertStatus(409)->assertJson(['success' => false]);

    expect(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('does not reserve a payment when the method has no gateway', function () {
    app()->singleton(
        PaymentGatewayRegistry::class,
        fn ($app) => new PaymentGatewayRegistry($app, []),
    );

    pay($this->order, $this->user)->assertStatus(422);

    expect(Payment::query()->where('order_id', $this->order->id)->exists())->toBeFalse();
});

it('does not let a late gateway result overwrite a settled payment, and flags it', function () {
    Log::shouldReceive('critical')->once();

    CallbackGateway::$callback = function (): void {
        Payment::query()->where('order_id', $this->order->id)->update(['status' => PaymentStatus::Failed]);
    };

    pay($this->order, $this->user)->assertStatus(402)->assertJson(['data' => ['status' => 'failed']]);
});

it('records the gateway outcome even if the order was cancelled during the call', function () {
    CallbackGateway::$callback = function (): void {
        Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Cancelled]);
    };

    pay($this->order, $this->user)->assertCreated();

    expect(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Successful);
});
