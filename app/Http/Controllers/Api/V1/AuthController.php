<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {

        /**
         * @var User|null $user
         */
        $user = User::query()
            ->where('email', (string) $request->string('email'))
            ->first();

        if (
            !$user ||
            !Hash::check((string) $request->string('password'), $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Your implementation goes here.
        $validated = $request->validated();
        $user = User::create($validated);

        event(new Registered($user));

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(
            $request->validated()
        );

        return response()->json([
            'message' => 'If an account exists with that email, a password reset link will be sent.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make((string) $request->string('password')),
        ])->save();

        // Revoke all Sanctum personal access tokens.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.',
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /**
         * @var User $user
         */
        $user = $request->user();

        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            event(new Registered($user));
        }

        return new UserResource($user->refresh());
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, string $password): void {
                /**
                 * @var User $user
                 */
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke existing Sanctum tokens after password recovery.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                //@phpstan-ignore-next-line
                'token' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
