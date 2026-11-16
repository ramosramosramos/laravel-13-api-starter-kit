<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a new user successfully', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Juan Dela Cruz')
        ->assertJsonPath('user.email', 'juan@example.com')
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'juan@example.com',
    ]);
});

it('rejects duplicate email addresses', function () {
    User::factory()->create([
        'email' => 'juan@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Another Juan',
        'email' => 'juan@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(
        User::where('email', 'juan@example.com')->count()
    )->toBe(1);
});
