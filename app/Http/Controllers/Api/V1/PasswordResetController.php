<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ForgotPasswordRequest;
use App\Http\Requests\V1\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

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

    public function reset(ResetPasswordRequest $request, ResetUserPassword $reset): JsonResponse
    {
        $status = $reset->handle(
            $request->only('email', 'password', 'password_confirmation', 'token')
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
