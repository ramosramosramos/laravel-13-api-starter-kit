<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('rejects requests without an access token', function () {
    $this
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('allows access with a valid Sanctum token', function () {
    $user = User::factory()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me');

    $response->assertOk();

    // Check authentication without assuming a particular JSON wrapper.
    expect(Auth::guard('sanctum')->user()?->id)
        ->toBe($user->id);
});

it('rejects invalid Sanctum tokens', function () {
    $this
        ->withToken('invalid-token-12345')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('rejects revoked Sanctum tokens', function () {
    $user = User::factory()->create();

    $accessToken = $user->createToken('auth-token');

    $token = $accessToken->plainTextToken;

    $accessToken->accessToken->delete();

    Auth::forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('does not allow users to update another users profile', function () {
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
            'name' => 'Updated Name',
        ])
        ->assertOk();

    expect($user->fresh()->name)
        ->toBe('Updated Name');

    expect($anotherUser->fresh()->name)
        ->toBe('Maria');
});

it('rejects password changes without authentication', function () {
    $this
        ->patchJson('/api/v1/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertUnauthorized();
});

it('rejects malformed bearer tokens', function () {
    $this
        ->withHeader('Authorization', 'Bearer malformed.token.value')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('prevents unverified users from accessing verified-only endpoints', function () {
    Route::middleware(['auth:sanctum', 'verified'])
        ->get('/api/test/verified-only', function () {
            return response()->json([
                'message' => 'Verified access granted.',
            ]);
        });

    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->getJson('/api/test/verified-only')
        ->assertForbidden();
});
