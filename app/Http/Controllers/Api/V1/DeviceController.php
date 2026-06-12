<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiDevice;
use Illuminate\Http\Request;

/**
 * Push-notification device registry. The app registers its device UUID
 * (and FCM token, when push is configured) after login and refreshes it
 * on token rotation; logout removes it. Delivery infrastructure lands in
 * a later PR — this is the registration half.
 */
class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_uuid' => 'required|string|max:64',
            'name' => 'nullable|string|max:255',
            'platform' => 'nullable|string|in:android,ios',
            'fcm_token' => 'nullable|string|max:4096',
            'app_version' => 'nullable|string|max:32',
        ]);

        $device = ApiDevice::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_uuid' => $validated['device_uuid'],
            ],
            [
                'name' => $validated['name'] ?? null,
                'platform' => $validated['platform'] ?? null,
                'fcm_token' => $validated['fcm_token'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'device' => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'platform' => $device->platform,
                'has_push_token' => $device->fcm_token !== null,
            ],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $deviceUuid)
    {
        ApiDevice::where('user_id', $request->user()->id)
            ->where('device_uuid', $deviceUuid)
            ->delete();

        return response()->json(['message' => __('Device unregistered.')]);
    }
}
