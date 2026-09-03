<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page a verification email links to.
 *
 * Like the reset page, this is opened in whatever browser reads the mail, so
 * it cannot rely on the app being installed or on anybody being signed in —
 * the signed URL is the proof of identity, not a session.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): View
    {
        $user = User::find($id);

        // The signature is already verified by middleware. This checks the
        // link is for this user's current address: the hash is of the email,
        // so a link stops working once the address it was sent to changes.
        $addressMatches = $user !== null
            && hash_equals($hash, sha1((string) $user->getEmailForVerification()));

        if (! $addressMatches) {
            return view('auth.verify-email', ['state' => 'invalid']);
        }

        if ($user->hasVerifiedEmail()) {
            return view('auth.verify-email', ['state' => 'already', 'email' => $user->email]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return view('auth.verify-email', ['state' => 'verified', 'email' => $user->email]);
    }
}
