<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('records a successful payment on a confirmed order', function () {
    config(['payments.simulation.credit_card.outcome' => 'successful']);
    $order = Order::factory()->for($this->user)->confirmed()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'method' => 'credit_card',
                'status' => 'successful',
            ],
        ]);

    expect(Payment::where('order_id', $order->id)->count())->toBe(1);
});

it('records a failed payment when the gateway is configured to fail', function () {
    config(['payments.simulation.credit_card.outcome' => 'failed']);
    $order = Order::factory()->for($this->user)->confirmed()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(402)
        ->assertJson(['data' => ['status' => 'failed']]);
});

it('rejects a second payment on an already-paid order with 409', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->for($order)->successful()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(409)->assertJson(['success' => false]);
    expect(Payment::where('order_id', $order->id)->count())->toBe(1);
});

it('allows a retry after a failed payment', function () {
    config(['payments.simulation.credit_card.outcome' => 'successful']);
    $order = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->for($order)->failed()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(201)
        ->assertJson(['data' => ['status' => 'successful']]);
    expect(Payment::where('order_id', $order->id)->count())->toBe(2);
});

it('rejects a payment on a pending order with 409', function () {
    $order = Order::factory()->for($this->user)->pending()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(409)->assertJson(['success' => false]);
    expect(Payment::where('order_id', $order->id)->exists())->toBeFalse();
});

it('rejects a payment on a cancelled order with 409', function () {
    $order = Order::factory()->for($this->user)->cancelled()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(409)->assertJson(['success' => false]);
});

it('rejects an unsupported payment method with 422', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'bitcoin']);

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonValidationErrors('method');
});

it('returns 404 when paying for a non-existent order', function () {
    $response = $this->actingAs($this->user, 'api')
        ->postJson('/api/orders/999999/payments', ['method' => 'credit_card']);

    $response->assertStatus(404);
});

it('returns 404 when paying for another user\'s order', function () {
    $order = Order::factory()->for(User::factory())->confirmed()->create();

    $response = $this->actingAs($this->user, 'api')
        ->postJson("/api/orders/{$order->id}/payments", ['method' => 'credit_card']);

    $response->assertStatus(404);
});
