<?php

/*
 * Cross-origin access to the API.
 *
 * The mobile app is not subject to CORS at all — it is not a browser — so the
 * only reason this file exists is the day somebody points a web client at the
 * same API. Leaving Laravel's default of "*" until then means that day starts
 * with any site on the internet able to call this API with a token it has
 * somehow obtained.
 *
 * Authentication is a bearer token rather than a cookie, so credentials stay
 * off by default: turning them on would allow a cross-site request to carry the
 * session, which is the mechanism CSRF depends on.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma-separated origins, e.g. "https://app.mygeneration.test".
    // Empty means no browser origin is allowed, which is correct for a
    // mobile-only deployment.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Requested-With'],

    // Read by the client so it can tell a replayed write from one that ran.
    'exposed_headers' => ['Idempotent-Replay'],

    'max_age' => 3600,

    'supports_credentials' => false,
];
