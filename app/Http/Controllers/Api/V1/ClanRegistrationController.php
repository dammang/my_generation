<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clans\DecideClanRegistration;
use App\Actions\Clans\SubmitClanRegistration;
use App\Enums\ClanRegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreClanRegistrationRequest;
use App\Http\Resources\V1\ClanRegistrationResource;
use App\Models\Clan;
use App\Models\ClanRegistration;
use App\Models\Person;
use App\Models\Scope;
use App\Models\Tribe;
use App\Services\Permissions\PermissionResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Requests to start a clan.
 *
 * The decisions live in DecideClanRegistration, which refuses a request
 * decided by its own author and one in a tribe the decider does not run. This
 * calls it and reports what it says rather than checking any of that twice.
 */
class ClanRegistrationController extends Controller
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    /**
     * The requester's own, plus anything they are able to decide.
     *
     * One list rather than two endpoints: somebody who runs a tribe is usually
     * also somebody who has asked for something, and making them look in two
     * places to find out where each stands is a poor trade for a filter.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $decidable = Scope::query()
            ->where('scopeable_type', 'tribe')
            ->get()
            ->filter(fn (Scope $scope) => $this->permissions->can($user, 'clans.manage', $scope->path))
            ->pluck('scopeable_id');

        $registrations = ClanRegistration::query()
            ->where(fn ($q) => $q
                ->where('requested_by', $user->getKey())
                ->orWhereIn('tribe_id', $decidable))
            ->with(['tribe', 'parentClan', 'ancestor', 'requester', 'clan'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
            )
            ->latest('id')
            ->get();

        return ApiResponse::success(ClanRegistrationResource::collection($registrations));
    }

    public function store(StoreClanRegistrationRequest $request, SubmitClanRegistration $submit): JsonResponse
    {
        $data = $request->validated();
        $tribe = Tribe::where('ulid', $data['tribe_ulid'])->firstOrFail();

        $registration = $submit->handle($request->user(), $tribe, [
            'name' => $data['name'],
            'native_name' => $data['native_name'] ?? null,
            'description' => $data['description'] ?? null,
            'statement' => $data['statement'] ?? null,
            'parent_clan_id' => isset($data['parent_clan_ulid'])
                ? Clan::where('ulid', $data['parent_clan_ulid'])->value('id')
                : null,
            'ancestor_person_id' => isset($data['ancestor_person_ulid'])
                ? Person::where('ulid', $data['ancestor_person_ulid'])->value('id')
                : null,
        ]);

        return ApiResponse::created(
            ClanRegistrationResource::make($registration->load(['tribe', 'requester'])),
        );
    }

    public function approve(Request $request, ClanRegistration $clanRegistration, DecideClanRegistration $decide): JsonResponse
    {
        $clan = $decide->approve(
            $clanRegistration,
            $request->user(),
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::success(
            ClanRegistrationResource::make(
                $clanRegistration->refresh()->load(['tribe', 'requester', 'clan']),
            ),
        );
    }

    public function reject(Request $request, ClanRegistration $clanRegistration, DecideClanRegistration $decide): JsonResponse
    {
        $decide->reject(
            $clanRegistration,
            $request->user(),
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::success(
            ClanRegistrationResource::make(
                $clanRegistration->refresh()->load(['tribe', 'requester']),
            ),
        );
    }

    /** Somebody changing their mind, which is not the same as being refused. */
    public function withdraw(Request $request, ClanRegistration $clanRegistration): JsonResponse
    {
        abort_unless($request->user()->is($clanRegistration->requester), 403);
        abort_unless($clanRegistration->isPending(), 409, 'This request has already been decided.');

        $clanRegistration->forceFill(['status' => ClanRegistrationStatus::Withdrawn])->save();

        return ApiResponse::success(ClanRegistrationResource::make($clanRegistration));
    }
}
