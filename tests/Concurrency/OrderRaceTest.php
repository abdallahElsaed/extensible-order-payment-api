<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Fixtures\SlowGateway;
use Tests\Support\RequestRace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::factory()->for($this->user)->confirmed()->create(['total' => 1000]);
    $this->headers = ['Authorization' => 'Bearer '.JWTAuth::fromUser($this->user)];
    $this->payment = [
        'method' => 'POST',
        'uri' => "/api/orders/{$this->order->id}/payments",
        'body' => ['method' => 'credit_card'],
        'headers' => $this->headers,
        'gateway' => SlowGateway::class,
    ];
});

it('never lets a payment slip in behind the has-payments check of a delete', function () {
    [$delete, $payment] = RequestRace::run([
        [
            'method' => 'DELETE',
            'uri' => "/api/orders/{$this->order->id}",
            'headers' => $this->headers,
            'pause_after_query' => 'from `payments`',
        ],
        [...$this->payment, 'wait_for_pause' => true],
    ]);

    expect($delete['status'])->toBe(200)
        ->and($payment['status'])->toBe(404)
        ->and(Order::find($this->order->id))->toBeNull()
        ->and(Payment::query()->exists())->toBeFalse();
});

it('never charges a stale total when items change during the has-payments check', function () {
    [$update, $payment] = RequestRace::run([
        [
            'method' => 'PATCH',
            'uri' => "/api/orders/{$this->order->id}",
            'headers' => $this->headers,
            'body' => ['items' => [['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => '25.00']]],
            'pause_after_query' => 'from `payments`',
        ],
        [...$this->payment, 'wait_for_pause' => true],
    ]);

    expect($update['status'])->toBe(200)
        ->and($payment['status'])->toBe(201)
        ->and(Order::query()->findOrFail($this->order->id)->total)->toBe(5000)
        ->and(Payment::query()->where('order_id', $this->order->id)->sole()->reference)->toBe('charged_5000');
});

it('checks a status transition against the locked row, not a stale read', function () {
    $this->order->update(['status' => OrderStatus::Pending]);
    $patch = ['method' => 'PATCH', 'uri' => "/api/orders/{$this->order->id}", 'headers' => $this->headers];

    [$cancel, $confirm] = RequestRace::run([
        [...$patch, 'body' => ['status' => 'cancelled'], 'wait_for_pause' => true],
        [...$patch, 'body' => ['status' => 'confirmed'], 'pause_after_query' => 'from `orders`'],
    ]);

    expect($cancel['status'])->toBe(200)
        ->and($confirm['status'])->toBe(409)
        ->and(Order::query()->findOrFail($this->order->id)->status)->toBe(OrderStatus::Cancelled);
});
