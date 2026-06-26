<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('ignores customer details sent in the request', function () {
    $order = Order::factory()->for($this->user)->create([
        'customer_name' => $this->user->name,
        'customer_email' => $this->user->email,
    ]);

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'customer_name' => 'Updated Name',
        'customer_email' => 'updated@example.com',
    ]);

    $response->assertOk()->assertJson(['data' => ['customer_name' => $this->user->name]]);
    expect($order->fresh()->customer_name)->toBe($this->user->name);
    expect($order->fresh()->customer_email)->toBe($this->user->email);
});

it('recalculates the total when items change', function () {
    $order = Order::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'items' => [
            ['product_name' => 'New', 'quantity' => 4, 'unit_price' => 12.50],
        ],
    ]);

    $response->assertOk()->assertJson(['data' => ['total' => '50.00']]);
    expect($order->fresh()->total)->toBe(5000);
    expect($order->fresh()->items)->toHaveCount(1);
});

it('allows a valid status transition', function () {
    $order = Order::factory()->for($this->user)->pending()->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'status' => OrderStatus::Confirmed->value,
    ]);

    $response->assertOk()->assertJson(['data' => ['status' => OrderStatus::Confirmed->value]]);
    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('rejects an invalid status transition with 409', function () {
    $order = Order::factory()->for($this->user)->cancelled()->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'status' => OrderStatus::Confirmed->value,
    ]);

    $response->assertStatus(409)->assertJson(['success' => false]);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('refuses to modify items once the order has payments', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create(['total' => 5000]);
    Payment::factory()->for($order)->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'items' => [
            ['product_name' => 'Tampered', 'quantity' => 9, 'unit_price' => 99.00],
        ],
    ]);

    $response->assertStatus(409)->assertJson(['success' => false]);
    expect($order->fresh()->total)->toBe(5000);
    expect($order->fresh()->items)->toHaveCount(0);
});

it('still allows a status-only change when the order has payments', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->for($order)->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'status' => OrderStatus::Cancelled->value,
    ]);

    $response->assertOk()->assertJson(['data' => ['status' => OrderStatus::Cancelled->value]]);
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});
