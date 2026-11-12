<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    /**
     * Registers or refreshes the FCM token of the signed-in ASN's device.
     *
     * Called right after login and again from FirebaseMessaging.onTokenRefresh,
     * so the device keeps receiving pushes after Firebase rotates the token.
     *
     * The token column is unique, therefore a token that moves to another
     * account is reassigned instead of duplicated: a phone signed out of user
     * A must never keep receiving user A's visitor requests after user B logs
     * in on the same phone.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'device_token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
        ], [
            'device_token.required' => 'Token perangkat wajib dikirim.',
            'device_token.max' => 'Token perangkat terlalu panjang.',
        ]);

        $user = Auth::user();

        // device_token is both the lookup key and a fillable column. Passing
        // it as a create attribute means the model ends up with the token in
        // its original state, so moving a token between accounts (same phone,
        // different ASN) silently keeps the previous user_id. The row is
        // therefore assigned column by column.
        $device = Device::where('device_token', '=', $payload['device_token'])->first();

        if ($device === null) {
            $device = new Device();
            $device->device_token = $payload['device_token'];
        }

        $device->user_id = $user->id;
        $device->device_name = $payload['device_name'] ?? ('Device ' . $user->name);
        $device->platform = $payload['platform'] ?? 'android';
        $device->last_used_at = now();
        $device->save();

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil didaftarkan.',
            'data' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
            ],
        ]);
    }

    /**
     * Removes the FCM token on logout so a signed-out phone stops receiving
     * pushes for the account that just logged out.
     *
     * Only the caller's own rows are touched, so a device token belonging to
     * another user can never be deleted through this route.
     */
    public function destroy(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'device_token' => ['required', 'string', 'max:255'],
        ], [
            'device_token.required' => 'Token perangkat wajib dikirim.',
        ]);

        Device::where('device_token', '=', $payload['device_token'])
            ->where('user_id', '=', Auth::user()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil dihapus.',
        ]);
    }
}
