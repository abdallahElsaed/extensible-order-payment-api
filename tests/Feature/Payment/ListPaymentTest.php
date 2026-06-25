<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists payments for a specific order, paginated', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->count(3)->for($order)->create();

    $response = $this->actingAs($this->user, 'api')
        ->getJson("/api/orders/{$order->id}/payments?per_page=2");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('lists all of the authenticated user payments across orders', function () {
    $orderA = Order::factory()->for($this->user)->confirmed()->create();
    $orderB = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->count(2)->for($orderA)->create();
    Payment::factory()->for($orderB)->create();

    $response = $this->actingAs($this->user, 'api')->getJson('/api/payments');

    $response->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(3, 'data');
});

it('does not expose another user payments in the global list', function () {
    $mine = Order::factory()->for($this->user)->confirmed()->create();
    Payment::factory()->for($mine)->create();

    $theirs = Order::factory()->for(User::factory())->confirmed()->create();
    Payment::factory()->count(5)->for($theirs)->create();

    $response = $this->actingAs($this->user, 'api')->getJson('/api/payments');

    $response->assertOk()->assertJsonPath('meta.total', 1);
});

it('retrieves a single payment by its uuid', function () {
    $order = Order::factory()->for($this->user)->confirmed()->create();
    $payment = Payment::factory()->for($order)->create();

    $response = $this->actingAs($this->user, 'api')->getJson("/api/payments/{$payment->uuid}");

    $response->assertOk()->assertJsonPath('data.id', $payment->uuid);
});

it('returns 404 for another user payment by uuid', function () {
    $theirs = Order::factory()->for(User::factory())->confirmed()->create();
    $payment = Payment::factory()->for($theirs)->create();

    $response = $this->actingAs($this->user, 'api')->getJson("/api/payments/{$payment->uuid}");

    $response->assertStatus(404);
});
