<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Claims\DecideProfileClaim;
use App\Actions\Claims\SubmitProfileClaim;
use App\Enums\ClaimStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProfileClaimResource;
use App\Models\Person;
use App\Models\ProfileClaim;
use App\Services\Privacy\ViewerScope;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileClaimController extends Controller
{
    /** The requester's own claims. */
    public function index(Request $request): JsonResponse
    {
        $claims = ProfileClaim::query()
            ->where('user_id', $request->user()->getKey())
            ->with('person')
            ->latest('id')
            ->get();

        return ApiResponse::success(ProfileClaimResource::collection($claims));
    }

    public function store(Request $request, SubmitProfileClaim $action): JsonResponse
    {
        $data = $request->validate([
            'person_ulid' => ['required', 'string', Rule::exists('people', 'ulid')->whereNull('deleted_at')],
            'evidence' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'relationship_statement' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        // The privacy predicate applies: you cannot claim — or discover — a
        // person you were not able to see in the first place.
        $person = Person::where('ulid', $data['person_ulid'])
            ->visibleTo(app(ViewerScope::class))
            ->firstOrFail();

        $claim = $action->handle(
            $request->user(),
            $person,
            $data['evidence'] ?? null,
            $data['relationship_statement'] ?? null,
        );

        return ApiResponse::created(ProfileClaimResource::make($claim->load('person')));
    }

    public function approve(Request $request, ProfileClaim $profileClaim, DecideProfileClaim $action): JsonResponse
    {
        $claim = $action->approve(
            $profileClaim,
            $request->user(),
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::success(ProfileClaimResource::make($claim->load(['person', 'user'])));
    }

    public function reject(Request $request, ProfileClaim $profileClaim, DecideProfileClaim $action): JsonResponse
    {
        $claim = $action->reject(
            $profileClaim,
            $request->user(),
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::success(ProfileClaimResource::make($claim->load(['person', 'user'])));
    }

    /** Withdrawing is always the claimant's own decision. */
    public function destroy(Request $request, ProfileClaim $profileClaim): JsonResponse
    {
        abort_unless($profileClaim->user_id === $request->user()->getKey(), 404);

        $profileClaim->update(['status' => ClaimStatus::Withdrawn]);

        return ApiResponse::noContent();
    }
}
