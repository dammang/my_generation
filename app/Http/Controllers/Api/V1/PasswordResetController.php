<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ForgotPasswordRequest;
use App\Http\Requests\V1\ResetPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Always the same answer, whether or not the address exists — otherwise
        // this endpoint enumerates accounts.
        return ApiResponse::success([
            'message' => 'If that email address has an account, a reset link is on its way.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Every existing session is invalidated: a password reset is
                // how somebody recovers an account that may be compromised.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error(
                'This reset link is invalid or has expired.',
                422,
                ['email' => [__($status)]],
            );
        }

        return ApiResponse::success(['message' => 'Your password has been reset.']);
    }
}
