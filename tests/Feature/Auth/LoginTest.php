<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('logs in a user with valid credentials', function () {
    User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'juan@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

    expect($response->json('token'))->toBeString();

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('rejects login with an incorrect password', function () {
    User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'juan@example.com',
        'password' => 'WrongPassword123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('requires an email and password', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
            'password',
        ]);
});

it('rejects login for an account that does not exist', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('allows access to a protected route with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'juan@example.com',
        'password' => 'Password123!',
    ]);

    $token = $loginResponse->json('token');

    expect($token)->toBeString()->not->toBeEmpty();

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.email', $user->email);
});
it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});
