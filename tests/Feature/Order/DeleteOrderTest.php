<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('deletes an order with no payments', function () {
    $order = Order::factory()->for($this->user)->create();

    $response = $this->actingAs($this->user, 'api')->deleteJson("/api/orders/{$order->id}");

    $response->assertOk()->assertJson(['success' => true]);
    expect(Order::find($order->id))->toBeNull();
});

it('refuses to delete an order that has payments with 409', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->for($order)->create();

    $response = $this->actingAs($this->user, 'api')->deleteJson("/api/orders/{$order->id}");

    $response->assertStatus(409)->assertJson(['success' => false]);
    expect(Order::find($order->id))->not->toBeNull();
});

it('returns 404 when deleting a non-existent order', function () {
    $response = $this->actingAs($this->user, 'api')->deleteJson('/api/orders/999999');

    $response->assertStatus(404);
});
