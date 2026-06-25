<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('updates customer details', function () {
    $order = Order::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user, 'api')->patchJson("/api/orders/{$order->id}", [
        'customer_name' => 'Updated Name',
    ]);

    $response->assertOk()->assertJson(['data' => ['customer_name' => 'Updated Name']]);
    expect($order->fresh()->customer_name)->toBe('Updated Name');
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
