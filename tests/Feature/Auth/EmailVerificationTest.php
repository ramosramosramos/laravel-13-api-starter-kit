<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('verifies an email using a valid signed URL', function () {
    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $response = $this
        ->withToken($token)
        ->getJson($verificationUrl);

    $response->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects an invalid email verification hash', function () {
    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1('wrong@example.com'),
        ]
    );

    $this
        ->withToken($token)
        ->getJson($verificationUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a tampered email verification signature', function () {
    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $separator = str_contains($verificationUrl, '?') ? '&' : '?';

    $tamperedUrl = $verificationUrl
        .$separator
        .'modified=true';

    $this
        ->withToken($token)
        ->getJson($tamperedUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired email verification URL', function () {
    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $this->travel(61)->minutes();

    try {
        $this
            ->withToken($token)
            ->getJson($verificationUrl)
            ->assertForbidden();

        expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    } finally {
        $this->travelBack();
    }
});

it('prevents changing the user ID in a signed verification URL', function () {
    $user = User::factory()->unverified()->create();

    $anotherUser = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $tamperedUrl = str_replace(
        '/verify/'.$user->id.'/',
        '/verify/'.$anotherUser->id.'/',
        $verificationUrl
    );

    $this
        ->getJson($tamperedUrl)
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    expect($anotherUser->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends email verification to an unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $token = $user->createToken('auth-token')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification');

    $response->assertOk();

    Notification::assertSentTo(
        $user,
        VerifyEmail::class
    );
});

it('rejects unauthenticated email verification resend requests', function () {
    $this
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertUnauthorized();
});

it('does not resend verification to an already verified user', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('auth-token')->plainTextToken;

    $this
        ->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk();

    Notification::assertNothingSent();
});

it('allows email verification without authentication using a valid signed URL', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $this
        ->getJson($verificationUrl)
        ->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
