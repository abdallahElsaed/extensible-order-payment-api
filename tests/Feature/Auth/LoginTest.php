<?php

declare(strict_types=1);

use App\Models\User;

it('logs in with valid credentials and returns a token', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'data' => [
                'user' => ['email' => 'jane@example.com'],
                'token_type' => 'bearer',
            ],
        ])
        ->assertJsonStructure([
            'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type', 'expires_in'],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with the wrong password', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson(['success' => false]);
});

it('validates required login fields', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects access to a protected route without a token', function () {
    $this->getJson('/api/user')->assertStatus(401);
});

it('rejects access to a protected route with an invalid token', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/api/user')
        ->assertStatus(401);
});

it('allows access to a protected route with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $token = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ])->json('data.token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user')
        ->assertSuccessful()
        ->assertJson(['id' => $user->id]);
});
