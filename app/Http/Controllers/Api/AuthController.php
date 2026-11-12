<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Mobile login: NIP + password, ASN only.
     *
     * Deliberately never reveals whether the NIP exists: unknown NIP, wrong
     * password and non-ASN role all return the same 401 body.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'nip' => ['required', 'string', 'digits:18'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
        ], [
            // Indonesian messages: the mobile app renders these verbatim.
            'nip.required' => 'NIP wajib diisi.',
            'nip.digits' => 'NIP harus 18 digit angka.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $user = User::with('role')
            ->where('nip', $credentials['nip'])
            ->first();

        if ($user === null || !Hash::check($credentials['password'], $user->password)) {
            return $this->authenticationFailed();
        }

        if (($user->role->code ?? null) !== Role::ASN) {
            return $this->authenticationFailed();
        }

        $this->registerDevice($user, $credentials);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'nip' => $user->nip,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'division_id' => $user->division_id,
                // Admin/divisions-less accounts must not crash the response.
                'division_name' => optional($user->division)->name,
                'image' => $user->image,
            ],
        ]);
    }

    /**
     * Mobile logout: revoke only the token that authenticated this request,
     * then drop the FCM device row for this user.
     *
     * Safe to call repeatedly: an already-revoked token can no longer reach
     * this route, and a missing device row is not an error.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => ['nullable', 'string'],
        ]);

        $token = $request->user()->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }

        if ($request->filled('device_token')) {
            Device::where('device_token', '=', $request->device_token)
                ->where('user_id', '=', $request->user()->id)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Keep the existing FCM registration behaviour. Token rows are keyed by
     * the token itself because the column is unique: a second account logging
     * in on the same phone must take the token over, otherwise the device
     * would keep receiving the previous account's visitor notifications.
     */
    private function registerDevice(User $user, array $credentials): void
    {
        if (empty($credentials['device_token'])) {
            return;
        }

        // device_token is both the lookup key and fillable, so a fill-based
        // upsert leaves the original state intact and the user_id keeps
        // pointing at the previous account. Assign the row explicitly to move
        // a token to the ASN that just logged in on this phone.
        $device = Device::where('device_token', '=', $credentials['device_token'])->first();

        if ($device === null) {
            $device = new Device();
            $device->device_token = $credentials['device_token'];
        }

        $device->user_id = $user->id;
        $device->device_name = $credentials['device_name'] ?? 'Device ' . $user->name;
        $device->platform = $credentials['platform'] ?? 'android';
        $device->last_used_at = now();
        $device->save();
    }

    /**
     * Single uniform failure response, so the caller cannot tell an unknown
     * NIP apart from a wrong password or a non-ASN account.
     */
    private function authenticationFailed(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'NIP atau password salah',
        ], 401);
    }
}
