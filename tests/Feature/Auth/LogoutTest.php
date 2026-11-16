<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an authenticated user to logout', function () {
    $user = User::factory()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects a token after logout', function () {
    $user = User::factory()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);

    // Reset cached authentication state between requests.
    Auth::forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('rejects logout without authentication', function () {
    $this
        ->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

it('revokes only the current device token', function () {
    $user = User::factory()->create();

    $laptopToken = $user
        ->createToken('laptop')
        ->plainTextToken;

    $phoneToken = $user
        ->createToken('phone')
        ->plainTextToken;

    $this->assertDatabaseCount('personal_access_tokens', 2);

    // Logout laptop.
    $this
        ->withToken($laptopToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 1);

    // Clear the cached authentication guard.
    Auth::forgetGuards();

    // Laptop token must be invalid.
    $this
        ->withToken($laptopToken)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    // Reset again before testing another token.
    Auth::forgetGuards();

    // Phone token should remain valid.
    $this
        ->withToken($phoneToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 1);
});
