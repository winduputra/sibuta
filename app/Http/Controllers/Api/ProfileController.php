<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function updatePassword()
    {
        $this->request->validate([
            'password' => ['required',]
        ]);

        $auth = Auth::user();
        
        $user = User::find($auth->id);

        $user->update(['password' => bcrypt($this->request->password)]);

        $user = $user->refresh();

        $user->division_name = $user->division->name;

        return response()->json(['success' => true, 'data' => $user]);
    }
}
