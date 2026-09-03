<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Auth\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * The page a reset email links to.
 *
 * This is the only web page the application serves. It exists because a reset
 * link is opened wherever somebody reads their mail — often a desktop, often
 * not the phone the app is installed on — so the reset cannot require the app.
 *
 * It is deliberately a plain form post rather than fetch(): somebody locked out
 * of their account is already having a bad day, and a page that needs
 * JavaScript to work is one more way for that to fail.
 */
class ResetPasswordController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, ResetUserPassword $reset): RedirectResponse
    {
        $status = $reset->handle(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Back to the form with the token intact, so an expired link and a
            // mistyped password do not look like the same failure.
            return back()
                ->withInput($request->only('email', 'token'))
                ->withErrors(['password' => __($status)]);
        }

        return redirect()->route('password.reset.done');
    }

    public function done(): View
    {
        return view('auth.reset-password-done');
    }
}
