<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherOrder = Order::factory()->for(User::factory())->create();
});

it('returns 404 when showing another user\'s order', function () {
    $this->actingAs($this->user, 'api')
        ->getJson("/api/orders/{$this->otherOrder->id}")
        ->assertStatus(404);
});

it('returns 404 when updating another user\'s order', function () {
    $this->actingAs($this->user, 'api')
        ->patchJson("/api/orders/{$this->otherOrder->id}", ['status' => OrderStatus::Confirmed->value])
        ->assertStatus(404);
});

it('returns 404 when deleting another user\'s order', function () {
    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/orders/{$this->otherOrder->id}")
        ->assertStatus(404);
});
