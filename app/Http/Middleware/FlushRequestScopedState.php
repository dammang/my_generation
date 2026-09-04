<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\V1\PersonResource;
use App\Services\Privacy\ViewerScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discards per-request container state at the start of every request.
 *
 * ViewerScope is bound as `scoped` so it resolves once and is shared by
 * policies, query scopes and every node of a serialised tree. That memoisation
 * is only safe if the instance is genuinely per-request — and it is not, in any
 * environment that reuses the container between requests: the test suite does,
 * and so does Octane.
 *
 * Without this, the second request in a process answers with the first
 * requester's entitlements. That is not a slow cache; it is one user seeing
 * another user's permissions.
 */
class FlushRequestScopedState
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->forgetInstance(ViewerScope::class);
        PersonResource::forgetRequestState();

        // Forgetting the container binding is not enough on its own.
        //
        // Route::getController() memoises the controller on the Route, and the
        // Route lives in the router's collection for the life of the process.
        // Eleven controllers take ViewerScope in their constructor, so the
        // instance built for the first request stays reachable through
        // $this->viewer no matter what the container is told to forget — and
        // the second request answers as the first requester. Proven by logging
        // spl_object_id: same controller, same scope, different authenticated
        // user.
        //
        // Clearing it here rather than dropping constructor injection from
        // eleven files keeps the guarantee in one place, and keeps it true for
        // the next controller somebody writes without knowing any of this.
        if (($route = $request->route()) !== null) {
            $route->controller = null;
        }

        return $next($request);
    }
}
