<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where to send a push.
 *
 * A registration belongs to one account on one device. Signing out removes it:
 * the next person to use that phone must not receive notifications about a
 * family they have nothing to do with.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        // FCM reissues a registration to whichever account most recently
        // claimed it. Keying on the token rather than on the user is what stops
        // a shared phone delivering one family's notifications to another.
        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->getKey(),
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return ApiResponse::success([
            'registered' => true,
            'platform' => $device->platform->value,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        DeviceToken::where('token', $data['token'])
            ->where('user_id', $request->user()->getKey())
            ->delete();

        return ApiResponse::noContent();
    }
}
