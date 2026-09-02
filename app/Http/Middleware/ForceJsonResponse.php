<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API always answers in JSON, even when a client forgets the Accept header
 * — otherwise Laravel redirects unauthenticated requests to a login route that
 * does not exist here, and the client sees a confusing 302 instead of a 401.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
