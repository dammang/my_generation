<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Clan;
use App\Models\FamilyBranch;
use App\Models\Media;
use App\Models\OralHistory;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonName;
use App\Models\Relationship;
use App\Models\Source;
use App\Models\Story;
use App\Models\Tribe;
use App\Models\Union;
use App\Models\User;
use App\Services\Privacy\ViewerScope;
use App\Services\Privacy\ViewerScopeResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * The requester's entitlements, resolved once per request and shared by
         * policies, query scopes, resources and cache keys.
         *
         * Bound lazily rather than populated by middleware: global API
         * middleware always runs before route middleware, so anything resolving
         * the scope up front would run before auth:sanctum had identified the
         * user, and every request would silently be treated as a guest. Reading
         * it on first use — which is always inside a controller, policy or
         * resource — cannot be ordered wrong.
         *
         * `scoped` rather than `singleton` so the instance is discarded between
         * requests under Octane. Outside a request (console, queue) there is no
         * authenticated user and this correctly yields a guest scope.
         */
        $this->app->scoped(
            ViewerScope::class,
            fn ($app) => $app->make(ViewerScopeResolver::class)->resolve($app['request']->user()),
        );
    }

    public function boot(): void
    {
        // Catch N+1 access during development. The tree endpoint has a fixed
        // query budget, and a lazily loaded relation is the usual way that
        // budget quietly becomes unbounded.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Stable morph aliases. Without them, polymorphic columns store fully
        // qualified class names, and moving or renaming a class silently
        // orphans every revision, citation and dispute pointing at it.
        $this->configureRateLimiting();
        $this->configureGates();

        Relation::enforceMorphMap([
            'person' => Person::class,
            'relationship' => Relationship::class,
            'union' => Union::class,
            'person_event' => PersonEvent::class,
            'person_name' => PersonName::class,
            'story' => Story::class,
            'source' => Source::class,
            'media' => Media::class,
            'tribe' => Tribe::class,
            'clan' => Clan::class,
            'family_branch' => FamilyBranch::class,
            'user' => User::class,
            'oral_history' => OralHistory::class,
        ]);
    }

    /**
     * Throttle buckets, sized to what each endpoint costs.
     *
     * Auth is limited per address as well as per IP: limiting only by IP lets a
     * botnet spread a credential-stuffing run across thousands of addresses,
     * and limiting only by email lets one IP enumerate accounts.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(5)->by((string) $request->input('email')),
        ]);

        RateLimiter::for('read', fn (Request $request) => Limit::perMinute(300)
            ->by($this->rateKey($request)));

        // Tree endpoints run recursive traversals; they get their own, tighter
        // bucket so a client looping over expansions cannot starve reads.
        RateLimiter::for('tree', fn (Request $request) => Limit::perMinute(120)
            ->by($this->rateKey($request)));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)
            ->by($this->rateKey($request)));

        RateLimiter::for('write', fn (Request $request) => Limit::perMinute(60)
            ->by($this->rateKey($request)));

        RateLimiter::for('upload', fn (Request $request) => Limit::perMinute(20)
            ->by($this->rateKey($request)));
    }

    private function rateKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }

    /**
     * A super admin bypasses every policy. Deliberately the only such
     * short-circuit: every other role goes through PermissionResolver, so
     * authority is always traceable to a scoped grant.
     */
    private function configureGates(): void
    {
        Gate::before(fn ($user) => $user->is_super_admin ? true : null);
    }
}
