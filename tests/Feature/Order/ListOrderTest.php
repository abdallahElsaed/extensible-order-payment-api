<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists only the authenticated user\'s orders with pagination meta', function () {
    Order::factory()->count(3)->for($this->user)->create();
    Order::factory()->count(2)->for(User::factory())->create();

    $response = $this->actingAs($this->user, 'api')->getJson('/api/orders');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'customer_name', 'status', 'total']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);

    expect($response->json('meta.total'))->toBe(3);
});

it('filters orders by status', function () {
    Order::factory()->count(2)->for($this->user)->pending()->create();
    Order::factory()->count(3)->for($this->user)->confirmed()->create();

    $response = $this->actingAs($this->user, 'api')->getJson('/api/orders?status=confirmed');

    $response->assertOk()->assertJsonCount(3, 'data');
    expect($response->json('meta.total'))->toBe(3);
});

it('returns an empty page when paging beyond range with valid meta', function () {
    Order::factory()->count(2)->for($this->user)->create();

    $response = $this->actingAs($this->user, 'api')->getJson('/api/orders?page=99');

    $response->assertOk()->assertJsonCount(0, 'data');
    expect($response->json('meta.total'))->toBe(2);
    expect($response->json('meta.current_page'))->toBe(99);
});

it('requires authentication to list orders', function () {
    $this->getJson('/api/orders')->assertStatus(401);
});
