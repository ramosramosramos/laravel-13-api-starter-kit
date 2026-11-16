<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // unauthenticated routes
    // unauthenticated routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1');

    Route::post('/auth/reset-password', [
        AuthController::class,
        'resetPassword',
    ])->middleware('throttle:6,1');

    Route::get('/auth/email/verify/{id}/{hash}', [
        EmailVerificationController::class,
        'verify',
    ])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // authenticated routes
    // authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post(
            '/auth/email/verification-notification',
            [EmailVerificationController::class, 'resend']
        )->middleware('throttle:6,1');
        Route::patch('/auth/change-password', [
            AuthController::class,
            'changePassword',
        ]);

        Route::patch('/auth/profile', [
            AuthController::class,
            'updateProfile',
        ]);

    });

});
