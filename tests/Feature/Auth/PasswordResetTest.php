<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('allows a registered user to request a password reset', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'juan@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'juan@example.com',
    ]);

    $response->assertOk();

    Notification::assertSentTo(
        $user,
        ResetPassword::class
    );
});

it('does not reveal whether an email is registered', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'unknown@example.com',
    ]);

    $response->assertOk();

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'juan@example.com',
        'token' => $token,
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertOk();

    expect(
        Hash::check('NewPassword456!', $user->fresh()->password)
    )->toBeTrue();

    expect(
        Hash::check('OldPassword123!', $user->fresh()->password)
    )->toBeFalse();
});

it('rejects an invalid password reset token', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'juan@example.com',
        'token' => 'invalid-token',
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ]);

    $response->assertUnprocessable();

    expect(
        Hash::check('OldPassword123!', $user->fresh()->password)
    )->toBeTrue();
});

it('rejects an expired password reset token', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = Password::broker()->createToken($user);

    $expiration = config('auth.passwords.users.expire', 60);

    $this->travel($expiration + 1)->minutes();

    try {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'juan@example.com',
            'token' => $token,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

        $response->assertUnprocessable();

        expect(
            Hash::check('OldPassword123!', $user->fresh()->password)
        )->toBeTrue();
    } finally {
        $this->travelBack();
    }
});

it('requires all password reset fields', function () {
    $response = $this->postJson('/api/v1/auth/reset-password', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
            'token',
            'password',
        ]);
});

it('prevents reusing a password reset token', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = Password::broker()->createToken($user);

    $payload = [
        'email' => 'juan@example.com',
        'token' => $token,
        'password' => 'NewPassword456!',
        'password_confirmation' => 'NewPassword456!',
    ];

    // First reset succeeds.
    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertOk();

    // The same token must not work again.
    $response = $this->postJson('/api/v1/auth/reset-password', [
        ...$payload,
        'password' => 'AnotherPassword789!',
        'password_confirmation' => 'AnotherPassword789!',
    ]);

    $response->assertUnprocessable();

    expect(
        Hash::check('NewPassword456!', $user->fresh()->password)
    )->toBeTrue();
});
