<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function form()
    {
        return view('auth.login');
    }

    public function login()
    {
        $this->request->validate([
            'username' => ['required'],
            'password' => ['required']
        ]);

        $payloads   = $this->request->only(['username', 'password']);

        if( Auth::attempt($payloads) ) {
            return redirect( route('user.list') );
        }

        return redirect( route('auth.login') )->withErrors(['Email/Kata Sandi salah']);
    }

    public function logout()
    {
        $auth = Auth::user();

        $redirect = route('auth.login');

        $this->request->session()->invalidate();

        $this->request->session()->regenerateToken();

        return redirect( $redirect );
    }
}
