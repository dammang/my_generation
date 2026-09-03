<?php

use App\Http\Controllers\Web\ResetPasswordController;
use App\Http\Controllers\Web\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * The reset link in a password email lands here. The route is named
 * `password.reset` because that is the name Laravel's own notification looks
 * for; AppServiceProvider builds the URL explicitly, but keeping the name
 * means the framework default would also resolve rather than throw.
 */
Route::get('/reset-password', [ResetPasswordController::class, 'show'])
    ->name('password.reset');

Route::post('/reset-password', [ResetPasswordController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::get('/reset-password/done', [ResetPasswordController::class, 'done'])
    ->name('password.reset.done');

/*
 * The link in a verification email lands here.
 *
 * Named `verification.verify` and shaped {id}/{hash} because that is exactly
 * what Laravel's own VerifyEmail notification signs a URL for — matching it
 * means the framework builds the link correctly instead of throwing, which is
 * the mistake the password reset link made.
 *
 * `signed` is the whole security model: nobody is logged in when they open
 * their mail, so the signature is what proves the link came from us, and
 * `throttle` keeps a valid link from being used to hammer the endpoint.
 */
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
