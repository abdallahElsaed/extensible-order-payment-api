<?php

declare(strict_types=1);

use App\Models\User;

it('registers a user and returns the user with a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
                'token_type' => 'bearer',
            ],
        ])
        ->assertJsonStructure([
            'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type', 'expires_in'],
        ]);

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects duplicate email registration', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('requires password confirmation to match', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'mismatch123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('requires a minimum password length of 8', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('requires name, email and password', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});
