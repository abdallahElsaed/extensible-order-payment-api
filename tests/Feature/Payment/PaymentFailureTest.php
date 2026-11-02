<?php

declare(strict_types=1);

use App\DTOs\Payment\GatewayResult;
use App\Enums\Order\OrderStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentService;
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

function expireInFlightPayment(): void
{
    test()->travel((int) config('payments.pending_ttl_minutes'))->minutes();
    app(PaymentService::class)->expireStale();
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

it('records a late success over the expiry and refuses any further charge', function () {
    Log::spy();
    $gatewayCalls = 0;

    CallbackGateway::$callback = function () use (&$gatewayCalls): void {
        $gatewayCalls++;
        expireInFlightPayment();
    };

    pay($this->order, $this->user)->assertCreated();

    $payment = Payment::query()->where('order_id', $this->order->id)->sole();

    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->reference)->toBe('callback_ref');

    pay($this->order, $this->user)->assertStatus(409);

    expect($gatewayCalls)->toBe(1)
        ->and(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
    Log::shouldHaveReceived('critical')->once();
});

it('keeps an expiry over a late non-success, but saves the reference and flags it', function (PaymentStatus $late, int $status) {
    Exceptions::fake();
    Log::spy();

    CallbackGateway::$callback = function () use ($late): GatewayResult {
        expireInFlightPayment();

        return new GatewayResult($late, 'late_ref', 'Late answer.');
    };

    pay($this->order, $this->user)->assertStatus($status);

    $payment = Payment::query()->where('order_id', $this->order->id)->sole();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->reference)->toBe('late_ref');
    Log::shouldHaveReceived('critical')->once();
})->with([
    'a decline' => [PaymentStatus::Failed, 402],
    'still pending' => [PaymentStatus::Pending, 409],
]);

it('records the gateway outcome even if the order was cancelled during the call', function () {
    CallbackGateway::$callback = function (): void {
        Order::query()->whereKey($this->order->id)->update(['status' => OrderStatus::Cancelled]);
    };

    pay($this->order, $this->user)->assertCreated();

    expect(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Successful);
});
