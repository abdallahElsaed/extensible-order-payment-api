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

it('throttles repeated login attempts for an email and IP with the API envelope', function () {
    $user = User::factory()->create(['password' => 'secret123']);
    $attempt = fn (string $password = 'wrong-password') => $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    foreach (range(1, 5) as $ignored) {
        $attempt()->assertStatus(401);
    }

    $attempt('secret123')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson(['success' => false, 'data' => null]);
});

it('counts an email in a different case towards the same limit', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'x'])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', ['email' => 'JANE@example.com', 'password' => 'x'])->assertStatus(429);
});

it('does not throttle a different email from the same IP', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'x'])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', ['email' => 'john@example.com', 'password' => 'x'])->assertStatus(401);
});

it('limits one IP trying many emails', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => "user{$attempt}@example.com", 'password' => 'x'])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', ['email' => 'another@example.com', 'password' => 'x'])->assertStatus(429);
});

it('answers 422 when the email is not a string, before the limiter can fail', function () {
    $this->postJson('/api/auth/login', ['email' => ['jane@example.com'], 'password' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});
