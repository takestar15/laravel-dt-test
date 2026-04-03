<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('users can issue access tokens with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'api@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson(route('api.tokens.store'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'postman',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_at',
        ]);

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

test('users can not issue access tokens with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'api@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson(route('api.tokens.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'postman',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
