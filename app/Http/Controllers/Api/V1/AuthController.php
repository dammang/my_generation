<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ExchangeFirebaseToken;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Requests\V1\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Privacy\ViewerScopeResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ViewerScopeResolver $scopes) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
                'locale' => $request->string('locale', 'en')->toString(),
            ]);

            // Registration never links an account to a genealogy record. That
            // happens only through an approved ProfileClaim — otherwise anyone
            // could register as somebody's deceased grandfather.
            $user->assignRole('contributor');

            return $user;
        });

        $this->audit($request, 'auth.registered', $user);

        return ApiResponse::created([
            'user' => UserResource::make($user->load('person')),
            'token' => $this->issueToken($user, $request),
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        // One message and one timing path for both branches, so the endpoint
        // cannot be used to discover which addresses have accounts.
        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active. Please contact an administrator.'],
            ]);
        }

        $user->forceFill(['last_active_at' => now()])->save();
        $this->audit($request, 'auth.logged_in', $user);

        return ApiResponse::success([
            'user' => UserResource::make($user->load('person')),
            'token' => $this->issueToken($user, $request),
        ]);
    }

    /**
     * Exchanges a verified Firebase identity for a session here.
     *
     * The ID token is checked once and discarded. Everything after this point
     * is the same Sanctum token the rest of the API already understands, which
     * is what keeps the permission model, the scopes and the policies working
     * unchanged — and what lets a session be revoked, which a Firebase token
     * cannot be.
     */
    public function firebase(Request $request, ExchangeFirebaseToken $exchange): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            'locale' => ['sometimes', 'string', 'max:12'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        ['user' => $user, 'created' => $created] = $exchange->handle(
            $data['id_token'],
            $data['locale'] ?? null,
        );

        $this->audit($request, $created ? 'auth.registered' : 'auth.logged_in', $user);

        return ApiResponse::success([
            'user' => UserResource::make($user->load('person')),
            'token' => $this->issueToken($user, $request),
            'created' => $created,
        ], status: $created ? 201 : 200);
    }

    public function logout(Request $request): JsonResponse
    {
        // Only the presented token is revoked. Signing out on a phone must not
        // sign the same person out on their other devices.
        $request->user()->currentAccessToken()?->delete();

        $this->audit($request, 'auth.logged_out', $request->user());

        return ApiResponse::success(['message' => 'Signed out.']);
    }

    public function logoutEverywhere(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        $this->audit($request, 'auth.logged_out_everywhere', $request->user());

        return ApiResponse::success(['message' => 'Signed out on all devices.']);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            UserResource::make($request->user()->load('person'))
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        // Changing the address invalidates the previous verification.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->scopes->forget($user);

        return ApiResponse::success(UserResource::make($user->load('person')));
    }

    private function issueToken(User $user, Request $request): string
    {
        $device = $request->string('device_name', 'api')->toString();

        return $user->createToken($device)->plainTextToken;
    }

    private function audit(Request $request, string $action, ?User $user): void
    {
        AuditLog::create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'auditable_type' => $user === null ? null : $user->getMorphClass(),
            'auditable_id' => $user?->getKey(),
            'ip_hash' => $request->ip() === null ? null : hash('sha256', $request->ip()),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
