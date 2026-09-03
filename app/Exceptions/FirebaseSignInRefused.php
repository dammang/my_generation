<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A verified identity that is still not allowed in.
 *
 * Firebase can say who somebody is; it knows nothing about whether an
 * administrator suspended them, or whether the email they are claiming already
 * belongs to an account here. Those are decisions made in this application.
 */
class FirebaseSignInRefused extends ApiException
{
    public function status(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'FIREBASE_SIGN_IN_REFUSED';
    }
}
