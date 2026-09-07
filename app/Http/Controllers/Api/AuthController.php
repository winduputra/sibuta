<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function login()
    {
        $this->request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        $payloads = $this->request->only(['username', 'password', 'device_token', 'device_name']);

        $user = User::where('username', 'like', $payloads['username'] . '%')->first();
        if( !$user ) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        if( !Hash::check($payloads['password'], $user->password) ) {
            return response()->json(['success' => false, 'message' => 'Password salah']);
        }

        if( isset($payloads['device_token']) ) {
            Device::create(['device_token' => $payloads['device_token'], 'device_name' => 'Device ' . $user->name , 'user_id' => $user->id]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true, 
            'data' => [
                'id' => $user->id,
                'name' => $user->name, 
                'username' => $user->username, 
                'token' => $token,
                'division_id' => $user->division_id,
                'division_name' => $user->division->name,
                'image' => $user->image,
            ],
        ]);
    }

    public function logout()
    {
        $auth = Auth::user();
        $this->request->validate([
            'device_token' => ['required'],
        ]);

        $device = Device::where('device_token', '=', $this->request->device_token)->where('user_id', '=', $auth->id)->first();

        if( $device ) {
            $device->delete();
        }

        return response()->json(['success' => true]);
    }
}
