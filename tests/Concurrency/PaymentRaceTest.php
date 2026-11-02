<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
