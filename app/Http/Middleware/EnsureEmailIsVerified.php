<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps unconfirmed addresses from writing to the archive.
 *
 * Reading is left alone. The point is not to make the app unusable until
 * somebody checks their mail — it is that a contribution is attributed to a
 * person forever, and an unconfirmed address is not yet evidence that the
 * person exists.
 *
 * Laravel ships EnsureEmailIsVerified, which aborts with a bare 403. This
 * returns the envelope the rest of the API uses, with a code the client can
 * branch on: "verify your email, here is the button to resend" is a different
 * screen from "you do not have permission", and a 403 alone cannot tell them
 * apart.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return ApiResponse::error(
                'Confirm your email address before adding to the archive. '
                    .'Check your inbox for the link, or ask for a new one.',
                403,
                code: 'EMAIL_NOT_VERIFIED',
                meta: ['email' => $user->getEmailForVerification()],
            );
        }

        return $next($request);
    }
}
