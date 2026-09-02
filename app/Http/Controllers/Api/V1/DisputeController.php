<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DisputeResource;
use App\Models\Dispute;
use App\Models\DisputeClaim;
use App\Models\Person;
use App\Services\Permissions\PermissionResolver;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "That is not what my grandmother said."
 *
 * A disagreement is opened against one field, and every version offered is kept
 * as a claim. Resolving does not delete the losing value — in a family archive
 * the fact that a question was open is itself worth keeping, and the answer can
 * turn out to be wrong later.
 */
class DisputeController extends Controller
{
    public function __construct(
        private readonly ViewerScope $viewer,
        private readonly PermissionResolver $permissions,
    ) {}

    /** Open disputes on one person. */
    public function forPerson(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $disputes = Dispute::query()
            ->where('disputable_type', $person->getMorphClass())
            ->where('disputable_id', $person->getKey())
            ->with(['openedBy:id,ulid,name', 'claims.claimedBy:id,ulid,name'])
            ->latest('id')
            ->get();

        return ApiResponse::success(DisputeResource::collection($disputes));
    }

    /**
     * Opens a dispute, or adds a competing claim to one already open.
     *
     * Two people disagreeing about the same field are in one argument, not two.
     * Opening a second dispute over the same field would split the evidence and
     * leave neither side able to see the other's.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'person_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'field' => ['required', 'string', 'max:64'],
            'claimed_value' => ['required', 'string', 'max:500'],
            'rationale' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $person = Person::where('ulid', $data['person_ulid'])
            ->visibleTo($this->viewer)
            ->firstOrFail();

        $this->authorize('view', $person);

        $dispute = DB::transaction(function () use ($data, $person, $request): Dispute {
            $dispute = Dispute::firstOrCreate(
                [
                    'disputable_type' => $person->getMorphClass(),
                    'disputable_id' => $person->getKey(),
                    'field' => $data['field'],
                    'status' => DisputeStatus::Open,
                ],
                ['opened_by' => $request->user()->getKey()],
            );

            DisputeClaim::create([
                'dispute_id' => $dispute->getKey(),
                'claimed_value' => $data['claimed_value'],
                'rationale' => $data['rationale'] ?? null,
                'claimed_by' => $request->user()->getKey(),
            ]);

            return $dispute;
        });

        return ApiResponse::created(
            DisputeResource::make($dispute->load(['openedBy:id,ulid,name', 'claims.claimedBy:id,ulid,name']))
        );
    }

    /**
     * Settles a dispute — by accepting a claim, or by recording that both
     * versions stand.
     */
    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        $this->assertMayResolve($request, $dispute);

        $data = $request->validate([
            'resolution' => ['required', Rule::enum(DisputeResolution::class)],
            'accepted_claim_id' => [
                'required_if:resolution,claim_accepted',
                'nullable',
                'integer',
                Rule::exists('dispute_claims', 'id')->where('dispute_id', $dispute->getKey()),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if ($dispute->status !== DisputeStatus::Open) {
            return ApiResponse::error('This disagreement has already been settled.', 422, code: 'DISPUTE_DECIDED');
        }

        $dispute->forceFill([
            'status' => DisputeStatus::Resolved,
            'resolution' => DisputeResolution::from($data['resolution']),
            'resolution_note' => $data['note'] ?? null,
            'accepted_claim_id' => $data['accepted_claim_id'] ?? null,
            'resolved_by' => $request->user()->getKey(),
            'resolved_at' => now(),
        ])->save();

        return ApiResponse::success(
            DisputeResource::make($dispute->fresh(['openedBy:id,ulid,name', 'claims.claimedBy:id,ulid,name']))
        );
    }

    /**
     * Settling a disagreement is a verifier's job, not the opener's — otherwise
     * whoever complains first decides.
     */
    private function assertMayResolve(Request $request, Dispute $dispute): void
    {
        $person = $dispute->disputable;

        $path = $person instanceof Person
            ? DB::table('scopes')
                ->where('scopeable_type', 'family_branch')
                ->where('scopeable_id', $person->family_branch_id)
                ->value('path')
            : null;

        $allowed = $request->user()->is_super_admin
            || $this->permissions->can($request->user(), 'disputes.resolve', $path)
            || $this->permissions->can($request->user(), 'people.verify', $path);

        if (! $allowed) {
            throw new AuthorizationException('You may not settle disagreements here.');
        }
    }
}
