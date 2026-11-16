<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('allows an authenticated user to update their name', function () {
    $user = User::factory()->create([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'name' => 'Juan Santos',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Juan Santos')
        ->assertJsonPath('data.email', 'juan@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Juan Santos',
        'email' => 'juan@example.com',
    ]);
});

it('allows an authenticated user to update their email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'email' => 'new@example.com',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'new@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'new@example.com',
    ]);
});

it('rejects an email already used by another user', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
    ]);

    User::factory()->create([
        'email' => 'maria@example.com',
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'email' => 'maria@example.com',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->fresh()->email)
        ->toBe('juan@example.com');
});

it('allows a user to keep their existing email', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'email' => 'juan@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'juan@example.com');
});

it('rejects an invalid email address', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'email' => 'invalid-email',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->fresh()->email)
        ->toBe('juan@example.com');
});

it('resets email verification when the email changes', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'email' => 'newjuan@example.com',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.email', 'newjuan@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo(
        $user,
        Illuminate\Auth\Notifications\VerifyEmail::class
    );
});

it('rejects unauthenticated profile updates', function () {
    $this
        ->patchJson('/api/v1/auth/profile', [
            'name' => 'Unauthorized User',
        ])
        ->assertUnauthorized();
});

it('only updates the authenticated user', function () {
    $user = User::factory()->create([
        'name' => 'Juan',
    ]);

    $anotherUser = User::factory()->create([
        'name' => 'Maria',
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/profile', [
            'id' => $anotherUser->id,
            'name' => 'Updated Juan',
        ])
        ->assertOk();

    expect($user->fresh()->name)
        ->toBe('Updated Juan');

    expect($anotherUser->fresh()->name)
        ->toBe('Maria');
});
