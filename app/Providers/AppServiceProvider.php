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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
}
