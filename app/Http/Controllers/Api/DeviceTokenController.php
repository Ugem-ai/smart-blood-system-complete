<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:10', 'max:4096'],
            'platform' => ['required', 'in:android,web'],
        ]);

        $user = $request->user();

        $deviceToken = DeviceToken::query()->updateOrCreate(
            [
                'token' => $validated['token'],
            ],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ]
        );

        ActivityLog::record($user->id, 'device-token.registered', [
            'device_token_id' => $deviceToken->id,
            'platform' => $deviceToken->platform,
        ]);

        return response()->json([
            'message' => 'Device token registered.',
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
                'last_used_at' => optional($deviceToken->last_used_at)->toISOString(),
            ],
        ]);
    }
}
