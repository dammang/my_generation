<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TreeRequest;
use App\Http\Resources\V1\PersonResource;
use App\Http\Resources\V1\TreeResource;
use App\Models\Person;
use App\Services\Privacy\ViewerScope;
use App\Services\Tree\DescendantCensus;
use App\Services\Tree\LineageDepthService;
use App\Services\Tree\RelationshipPathFinder;
use App\Services\Tree\TreeCache;
use App\Services\Tree\TreeTraversalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreeController extends Controller
{
    public function __construct(
        private readonly TreeTraversalService $traversal,
        private readonly TreeCache $cache,
        private readonly ViewerScope $viewer,
    ) {}

    /**
     * The tree around one person.
     *
     * Route binding already applied the privacy predicate, so an unauthorised
     * focus person 404s before any traversal runs.
     */
    public function show(TreeRequest $request, Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $ancestors = $request->ancestors();
        $descendants = $request->descendants();
        $budget = $request->budget();

        $graphVersion = (int) DB::table('tribes')
            ->where('id', $person->tribe_id)
            ->value('graph_version');

        $key = $this->cache->key($person, $this->viewer, $ancestors, $descendants, $budget, $graphVersion);

        $payload = $this->cache->remember($key, function () use ($person, $ancestors, $descendants, $budget, $request) {
            $graph = $this->traversal->build(
                focus: $person,
                ancestors: $ancestors,
                descendants: $descendants,
                budget: $budget,
                includeSpouses: $request->includes('spouses'),
            );

            return ['data' => TreeResource::make($graph), 'meta' => TreeResource::meta($graph)];
        });

        $etag = $this->cache->etag($payload);

        // A phone revisiting an unchanged tree transfers nothing.
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return ApiResponse::success($payload['data'], meta: $payload['meta'])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age=0, must-revalidate');
    }

    /** Ancestors only — the pedigree view. */
    public function ancestors(TreeRequest $request, Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $graph = $this->traversal->build(
            focus: $person,
            ancestors: $request->ancestors(),
            descendants: 0,
            budget: $request->budget(),
            includeSpouses: $request->includes('spouses'),
        );

        return ApiResponse::success(TreeResource::make($graph), meta: TreeResource::meta($graph));
    }

    /** Descendants only — the fan-out view, and the dangerous direction. */
    public function descendants(TreeRequest $request, Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $graph = $this->traversal->build(
            focus: $person,
            ancestors: 0,
            descendants: $request->descendants(),
            budget: $request->budget(),
            includeSpouses: $request->includes('spouses'),
        );

        return ApiResponse::success(TreeResource::make($graph), meta: TreeResource::meta($graph));
    }

    /**
     * The direct line up to the family branch's apical ancestor — "show my
     * lineage", and where the generation number comes from.
     */
    public function lineage(Person $person, LineageDepthService $lineage): JsonResponse
    {
        $this->authorize('view', $person);

        $line = $lineage->lineage($person);
        $depth = $lineage->forPerson($person);

        return ApiResponse::success(
            [
                'line' => PersonResource::collection(collect($line))->resolve(),
                'generation' => $depth,
            ],
            meta: [
                'length' => count($line),
                // A range rather than a number when cousins married upstream:
                // the person genuinely sits at two different depths.
                'generation_display' => $depth === null
                    ? null
                    : ($depth['collapsed']
                        ? "Generation {$depth['min_depth']}–{$depth['max_depth']}"
                        : 'Generation '.$depth['depth']),
            ],
        );
    }

    /**
     * Everything a chart's caption needs about one person.
     *
     * Counted from the graph, not from whatever the client fetched: a tree
     * drawn three generations deep would otherwise caption itself as a family
     * three generations large.
     */
    public function summary(Person $person, DescendantCensus $census): JsonResponse
    {
        $this->authorize('view', $person);

        $person->load([
            'clan:id,ulid,name,ancestor_person_id,counting_origin_person_id,generation_offset',
            'clan.ancestor:id,display_name',
            'clan.countingOrigin:id,display_name',
            'familyBranch:id,ulid,name,ancestor_person_id,generation_offset',
            'familyBranch.ancestor:id,display_name',
            'lineageDepths:person_id,root_person_id,depth',
            'generation',
        ]);

        return ApiResponse::success([
            'person' => PersonResource::make($person),
            ...$census->forPerson($person, $this->viewer),
        ]);
    }

    /**
     * The line from the top of the clan down to one person, as a list.
     *
     * What a family recites: one name per generation, in the order they are
     * said, each carrying both of the numbers the clan counts by.
     */
    public function directLine(Person $person, LineageDepthService $lineage): JsonResponse
    {
        $this->authorize('view', $person);

        return ApiResponse::success(
            PersonResource::collection(collect($lineage->directLine($person))),
        );
    }

    /** "How am I related to this person?" */
    public function pathTo(
        Request $request,
        Person $person,
        Person $other,
        RelationshipPathFinder $finder,
    ): JsonResponse {
        $this->authorize('view', $person);
        $this->authorize('view', $other);

        $result = $finder->between($person, $other);

        return ApiResponse::success([
            'related' => $result['related'],
            'label' => $result['label'],
            'common_ancestor' => $result['common_ancestor'] === null
                ? null
                : PersonResource::make($result['common_ancestor'])->resolve(),
            'generations_up' => $result['up'],
            'generations_down' => $result['down'],
        ]);
    }
}
