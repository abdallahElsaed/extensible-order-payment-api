<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentStatus;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Fixtures\CallbackGateway;
use Tests\Fixtures\CrashingGateway;
use Tests\Fixtures\SlowGateway;
use Tests\Support\RequestRace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::factory()->for($this->user)->confirmed()->create();
    $this->payment = fn (array $overrides = []): array => [
        'method' => 'POST',
        'uri' => "/api/orders/{$this->order->id}/payments",
        'body' => ['method' => 'credit_card'],
        'headers' => ['Authorization' => 'Bearer '.JWTAuth::fromUser($this->user)],
        'gateway' => SlowGateway::class,
        ...$overrides,
    ];
});

afterEach(function () {
    CallbackGateway::$callback = null;
});

it('lets one of many concurrent payment requests through and refuses the rest', function () {
    $responses = RequestRace::run(array_fill(0, 6, ($this->payment)()));

    $statuses = collect($responses)->pluck('status')->sort()->values()->all();

    expect($statuses)->toBe([201, 409, 409, 409, 409, 409])
        ->and(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('turns concurrent duplicates with one key into a single payment', function () {
    $duplicate = ($this->payment)(['headers' => [...($this->payment)()['headers'], 'Idempotency-Key' => 'race-key']]);

    $responses = collect(RequestRace::run(array_fill(0, 6, $duplicate)));

    $payment = Payment::query()->where('order_id', $this->order->id)->sole();

    expect($responses->pluck('status')->contains(201))->toBeTrue()
        ->and($responses->pluck('status')->diff([201, 202])->all())->toBe([])
        ->and($responses->pluck('body.data.id')->unique()->all())->toBe([$payment->uuid])
        ->and(IdempotencyKey::query()->count())->toBe(1);
});

it('lets the unique constraint settle one key racing on two orders', function () {
    $otherOrder = Order::factory()->for($this->user)->confirmed()->create();
    $headers = [...($this->payment)()['headers'], 'Idempotency-Key' => 'cross-order-key'];
    $pauseInsideTheWindow = ['headers' => $headers, 'pause_after_query' => 'from `idempotency_keys`'];

    $responses = RequestRace::run([
        ($this->payment)($pauseInsideTheWindow),
        ($this->payment)([...$pauseInsideTheWindow, 'uri' => "/api/orders/{$otherOrder->id}/payments"]),
    ]);

    expect(collect($responses)->pluck('status')->sort()->values()->all())->toBe([201, 422])
        ->and(Payment::query()->count())->toBe(1)
        ->and(IdempotencyKey::query()->count())->toBe(1);
});

it('holds no lock on the order during the gateway call', function () {
    config(['database.connections.probe' => config('database.connections.mysql')]);
    useCreditCardGateway(CallbackGateway::class);
    $orderWasLocked = null;

    CallbackGateway::$callback = function () use (&$orderWasLocked): void {
        try {
            DB::connection('probe')->table('orders')->where('id', $this->order->id)->lock('FOR UPDATE NOWAIT')->first();
            $orderWasLocked = false;
        } catch (QueryException) {
            $orderWasLocked = true;
        }
    };

    $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'credit_card'])
        ->assertCreated();

    expect($orderWasLocked)->toBeFalse();
});

it('answers 202 and flags the known result when it cannot be recorded', function () {
    Exceptions::fake();
    Log::shouldReceive('critical')->once();
    config(['database.connections.probe' => config('database.connections.mysql')]);
    useCreditCardGateway(CallbackGateway::class);
    $rival = DB::connection('probe');

    CallbackGateway::$callback = function () use ($rival): void {
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $rival->beginTransaction();
        $rival->table('payments')->where('order_id', $this->order->id)->lockForUpdate()->get();
    };

    try {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'credit_card']);
    } finally {
        $rival->rollBack();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    }

    $response->assertStatus(202);

    expect(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Pending);
});

it('takes the order lock before recording a late result', function () {
    Exceptions::fake();
    Log::shouldReceive('critical')->twice();
    config(['database.connections.probe' => config('database.connections.mysql')]);
    useCreditCardGateway(CallbackGateway::class);
    $rival = DB::connection('probe');

    CallbackGateway::$callback = function () use ($rival): void {
        Payment::query()->where('order_id', $this->order->id)->update(['status' => PaymentStatus::Failed]);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $rival->beginTransaction();
        $rival->table('orders')->where('id', $this->order->id)->lockForUpdate()->get();
    };

    try {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'credit_card']);
    } finally {
        $rival->rollBack();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    }

    $response->assertStatus(409);

    expect(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Failed);
});

it('recovers a payment whose process was killed during the gateway call', function () {
    [$crashed] = RequestRace::run([($this->payment)(['gateway' => CrashingGateway::class])]);

    expect($crashed['status'])->toBeNull()
        ->and(Payment::query()->where('order_id', $this->order->id)->sole()->status)->toBe(PaymentStatus::Pending);

    $this->actingAs($this->user, 'api');
    $retry = fn () => $this->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'credit_card']);

    $retry()->assertStatus(409);

    $this->travel((int) config('payments.pending_ttl_minutes'))->minutes();
    $this->artisan('payments:expire-stale')->assertSuccessful();

    $retry()->assertCreated();

    expect(Payment::query()->where('order_id', $this->order->id)->pluck('status')->all())
        ->toBe([PaymentStatus::Failed, PaymentStatus::Successful]);
});
