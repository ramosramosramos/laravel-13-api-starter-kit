<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows an authenticated user to change their password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

    $response->assertOk()
        ->assertJsonPath(
            'message',
            'Password changed successfully. Please log in again.'
        );

    expect(
        Hash::check('NewPassword456!', $user->fresh()->password)
    )->toBeTrue();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects an incorrect current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    expect(
        Hash::check('OldPassword123!', $user->fresh()->password)
    )->toBeTrue();

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('rejects mismatched password confirmation', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(
        Hash::check('OldPassword123!', $user->fresh()->password)
    )->toBeTrue();
});

it('rejects using the current password as the new password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'OldPassword123!',
            'password_confirmation' => 'OldPassword123!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
it('rejects unauthenticated password changes', function () {
    $this
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertUnauthorized();
});

it('revokes all device tokens after changing password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123!'),
    ]);

    $laptopToken = $user->createToken('laptop')->plainTextToken;
    $user->createToken('phone');

    $this->assertDatabaseCount('personal_access_tokens', 2);

    $this
        ->withToken($laptopToken)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('allows login with the new password after a password change', function () {
    $user = User::factory()->create([
        'email' => 'juan@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertOk();

    // Verify password was updated in database.
    expect(
        Hash::check('NewPassword456!', $user->fresh()->password)
    )->toBeTrue();

    // Remove the previous Authorization header.
    $this->withHeaders([
        'Authorization' => '',
    ]);

    // Clear cached authentication guards.
    app('auth')->forgetGuards();

    // Show the actual exception if login fails.
    $this->withoutExceptionHandling();

    $this
        ->postJson('/api/v1/auth/login', [
            'email' => 'juan@example.com',
            'password' => 'NewPassword456!',
        ])
        ->assertOk();
});
