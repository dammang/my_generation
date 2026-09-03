<?php

use App\Http\Controllers\Web\ResetPasswordController;
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
