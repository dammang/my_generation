<?php

use App\Http\Controllers\Web\ResetPasswordController;
use App\Http\Controllers\Web\VerifyEmailController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/*
 * The Flutter client owns the site root.
 *
 * Its shell is app.html rather than index.html on purpose: an index.html
 * beside Laravel's index.php makes which one answers "/" a question about the
 * web server's DirectoryIndex order, and the answer differs between machines.
 * Serving it from a route is the same everywhere.
 */
Route::get('/', fn (): Response => response()->file(public_path('app.html')))
    ->name('web-client');

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

/*
 * Where the client used to live. Kept so a bookmark or a link somebody shared
 * still arrives somewhere, rather than at a 404 with no explanation.
 */
Route::get('/app/{path?}', fn (): RedirectResponse => redirect('/', 301))
    ->where('path', '.*')
    ->name('web-client.legacy');

/*
 * Everything the app routes on the client — /tree, /person/01ABC — has no file
 * and no route behind it. Without this, opening one directly, or reloading the
 * page you are already on, is a 404.
 *
 * Last in the file and last in Laravel's matching order, so every real route
 * above still wins.
 */
Route::fallback(function (Request $request): Response {
    // The API answers its own 404s as JSON. A client handed an HTML page where
    // it expected an envelope reports something that has nothing to do with
    // what went wrong.
    abort_if($request->is('api/*'), 404);

    // And a missing asset must stay a missing asset: answering every unmatched
    // path with the shell means a 404 arrives as 200 HTML, and the failure that
    // follows names something else entirely.
    abort_unless($request->accepts(['text/html']), 404);

    return response()->file(public_path('app.html'));
});
