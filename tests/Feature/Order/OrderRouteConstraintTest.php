<?php

declare(strict_types=1);

use App\Models\User;

it('answers 404 for an order id that is not a storable integer', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create(), 'api')
        ->json($method, $uri, ['method' => 'credit_card'])
        ->assertNotFound()
        ->assertJson(['success' => false]);
})->with([
    'show' => ['GET', '/api/orders/abc'],
    'update with PATCH' => ['PATCH', '/api/orders/abc'],
    'update with PUT' => ['PUT', '/api/orders/abc'],
    'delete' => ['DELETE', '/api/orders/abc'],
    'pay' => ['POST', '/api/orders/abc/payments'],
    'list payments' => ['GET', '/api/orders/abc/payments'],
    'pay, id too large for an integer' => ['POST', '/api/orders/99999999999999999999/payments'],
]);
