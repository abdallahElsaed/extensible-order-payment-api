<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates an order and auto-sums the total', function () {
    $response = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 3, 'unit_price' => 19.99],
            ['product_name' => 'Gadget', 'quantity' => 2, 'unit_price' => 5.00],
        ],
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'customer_name' => 'Jane Doe',
                'status' => OrderStatus::Pending->value,
                'total' => '69.97',
            ],
        ])
        ->assertJsonStructure([
            'data' => ['id', 'customer_name', 'customer_email', 'status', 'total', 'items' => [['id', 'product_name', 'quantity', 'unit_price', 'line_total']]],
        ]);

    $order = Order::first();
    expect($order->user_id)->toBe($this->user->id);
    expect($order->total)->toBe(6997);
    expect($order->items)->toHaveCount(2);
});

it('defaults the status to pending', function () {
    $response = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
        ],
    ]);

    $response->assertCreated();
    expect(Order::first()->status)->toBe(OrderStatus::Pending);
});

it('rejects an order with no items', function () {
    $response = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('items');
});

it('rejects zero or negative quantities', function (int $quantity) {
    $response = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => $quantity, 'unit_price' => 10.00],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
})->with([0, -1]);

it('rejects a negative unit price', function () {
    $response = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => -5.00],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('items.0.unit_price');
});

it('requires authentication', function () {
    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'items' => [
            ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 10.00],
        ],
    ]);

    $response->assertStatus(401);
});
