<?php

namespace App\Http\Controllers;

use App\Support\HomeRedirector;
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
            // Land on the page the authenticated user is actually allowed to open.
            // Admin-only pages would return 403 for an ASN account.
            return redirect( HomeRedirector::urlFor(Auth::user()) );
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
