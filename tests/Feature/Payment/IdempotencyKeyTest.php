<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentStatus;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Gateways\CreditCardGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\CallbackGateway;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::factory()->for($this->user)->confirmed()->create();
    $this->gatewayCalls = 0;

    useCreditCardGateway(CallbackGateway::class);
    CallbackGateway::$callback = function (): void {
        $this->gatewayCalls++;
    };
});

afterEach(function () {
    CallbackGateway::$callback = null;
});

function payWithKey(string $key, Order $order, array $body = ['method' => 'credit_card'], ?User $user = null): TestResponse
{
    return test()->actingAs($user ?? $order->user, 'api')
        ->withHeader('Idempotency-Key', $key)
        ->postJson("/api/orders/{$order->id}/payments", $body);
}

it('returns the first result on a replay without charging again', function () {
    $first = payWithKey('key-1', $this->order)->assertCreated();
    $replay = payWithKey('key-1', $this->order)->assertCreated();

    expect($replay->getContent())->toBe($first->getContent())
        ->and($this->gatewayCalls)->toBe(1)
        ->and(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('replays a declined payment instead of retrying it', function () {
    useCreditCardGateway(CreditCardGateway::class);
    config(['payments.simulation.credit_card.outcome' => 'failed']);
    $first = payWithKey('key-1', $this->order)->assertStatus(402);

    config(['payments.simulation.credit_card.outcome' => 'successful']);

    expect(payWithKey('key-1', $this->order)->assertStatus(402)->getContent())->toBe($first->getContent());
});

it('still replays after the order can no longer be paid', function () {
    $first = payWithKey('key-1', $this->order)->assertCreated();

    $this->order->update(['status' => 'cancelled']);

    expect(payWithKey('key-1', $this->order)->assertCreated()->getContent())->toBe($first->getContent());
});

it('replays an unknown outcome as pending, then as the expired failure', function () {
    Exceptions::fake();
    CallbackGateway::$callback = fn () => throw new ConnectionException('Timed out');

    payWithKey('key-1', $this->order)->assertStatus(202);
    payWithKey('key-1', $this->order)->assertStatus(202)->assertJson(['data' => ['status' => 'pending']]);

    $this->travel((int) config('payments.pending_ttl_minutes'))->minutes();
    $this->artisan('payments:expire-stale')->assertSuccessful();

    payWithKey('key-1', $this->order)->assertStatus(402)->assertJson(['data' => ['status' => 'failed']]);
    expect(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('rejects the same key with a different body', function (array $body) {
    payWithKey('key-1', $this->order)->assertCreated();

    payWithKey('key-1', $this->order, $body)
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonValidationErrors('Idempotency-Key');

    expect($this->gatewayCalls)->toBe(1)
        ->and(Payment::query()->where('order_id', $this->order->id)->sole()->method->value)->toBe('credit_card');
})->with([
    'another method' => [['method' => 'paypal']],
    'an extra field' => [['method' => 'credit_card', 'note' => 'changed']],
]);

it('treats a reordered body as the same request', function () {
    payWithKey('key-1', $this->order, ['method' => 'credit_card', 'note' => 'a'])->assertCreated();

    payWithKey('key-1', $this->order, ['note' => 'a', 'method' => 'credit_card'])->assertCreated();

    expect($this->gatewayCalls)->toBe(1);
});

it('rejects the same key on a different order', function () {
    $otherOrder = Order::factory()->for($this->user)->confirmed()->create();

    payWithKey('key-1', $this->order)->assertCreated();
    payWithKey('key-1', $otherOrder)->assertStatus(422);

    expect(Payment::query()->where('order_id', $otherOrder->id)->exists())->toBeFalse();
});

it('scopes keys to the user', function () {
    $otherUser = User::factory()->create();
    $otherOrder = Order::factory()->for($otherUser)->confirmed()->create();

    payWithKey('key-1', $this->order)->assertCreated();
    payWithKey('key-1', $otherOrder, user: $otherUser)->assertCreated();

    expect($this->gatewayCalls)->toBe(2);
});

it('treats keys differing only by case as different keys', function () {
    $otherOrder = Order::factory()->for($this->user)->confirmed()->create();

    payWithKey('key-abc', $this->order)->assertCreated();
    payWithKey('KEY-ABC', $otherOrder)->assertCreated();

    expect(IdempotencyKey::query()->count())->toBe(2);
});

it('does not consume a key on a request refused before any payment was attempted', function () {
    $order = Order::factory()->for($this->user)->pending()->create();

    payWithKey('key-1', $order)->assertStatus(409);

    $order->update(['status' => 'confirmed']);

    payWithKey('key-1', $order)->assertCreated();
});

it('rejects a malformed key', function (string $key) {
    payWithKey($key, $this->order)->assertStatus(422)->assertJsonValidationErrors('Idempotency-Key');

    expect(Payment::query()->exists())->toBeFalse();
})->with([
    'empty' => '',
    'too long' => str_repeat('a', 256),
    'contains a space' => 'key 1',
    'non-ascii' => 'clé',
]);

it('accepts a key of the maximum length', function () {
    payWithKey(str_repeat('a', 255), $this->order)->assertCreated();
});

it('enforces key uniqueness per user in the database itself', function () {
    $existing = IdempotencyKey::factory()->create();

    IdempotencyKey::factory()->create(['user_id' => $existing->user_id, 'key_hash' => $existing->key_hash]);
})->throws(UniqueConstraintViolationException::class);

it('stores the first payment against the key', function () {
    payWithKey('key-1', $this->order)->assertCreated();

    expect(IdempotencyKey::query()->sole()->payment->status)->toBe(PaymentStatus::Successful);
});
