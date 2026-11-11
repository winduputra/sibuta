<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function profile()
    {
        $auth = Auth::user();
        
        $user = User::find($auth->id);

        $user->division_name = $user->division->name;

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function updateImage()
    {
        $this->request->validate([
            'image' => ['required', 'image']
        ]);

        $auth = Auth::user();
        
        $user = User::find($auth->id);

        $image = $this->request->file('image');
        $extension = strtolower($image->getClientOriginalExtension());
        $name = strtotime(date('Y-m-d H:i:s') . mt_rand(1000, 9999));
        
        $filename = $image->storeAs('image', $name . '.' . $extension);

        $user->update(['image' => url('image/' . $name . '.' . $extension)]);

        $user = $user->refresh();

        $user->division_name = $user->division->name;

        return response()->json(['success' => true, 'data' => $user]);
    }

    /**
     * Changes the password of the authenticated ASN from the mobile app.
     *
     * The old password must be proven before the new one is accepted, the new
     * password must be confirmed and meet the strength rules, and only the hash
     * is ever persisted. The current Sanctum token is kept so the ASN is not
     * signed out of the device they are using.
     */
    public function updatePassword()
    {
        $this->request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                'different:current_password',
            ],
        ], [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.max' => 'Password baru maksimal 255 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $auth = Auth::user();

        $user = User::find($auth->id);

        if (!$user || !Hash::check($this->request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->update([
            'password' => Hash::make($this->request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
