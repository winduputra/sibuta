<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
        $users = User::get();

        return view('user.list', compact('users'));
    }

    public function form()
    {
        $divisions = Division::all();
        return view('user.form', compact('divisions'));
    }

    public function store()
    {
        $this->request->validate([
            'username' => ['unique:users']
        ]);

        $payloads = $this->request->only(['password', 'name', 'division_id', 'username']);


        if( isset($payloads['password']) OR !$payloads['password'] ) {
            $payloads['password'] = 'password';
        }

        $payloads['password'] = bcrypt($payloads['password']);

        $user = User::create($payloads);

        return redirect( route('user.list') )->with('success', 'Berhasil Menambahkan User ' . $user->name . ' (' . $user->username . ')');
    }

    public function edit($id)
    {
        $user = User::find($id);
        $divisions = Division::all();
        return view('user.form', compact('user', 'divisions'));
    }

    public function update($id)
    {
        $user = User::find($id);

        $payloads = $this->request->only(['password', 'name', 'username', 'division_id']);
        if( isset($payloads['username']) AND $user->username != $payloads['username'] ) {
            $this->request->validate([
                'username' => 'unique:users'
            ]);
        }

        if( isset($payloads['password']) ) {
            $payloads['password'] = bcrypt($payloads['password']);
        }

        $user->update($payloads);

        $user->refresh();
        
        return redirect( route('user.list') )->with('success', 'Berhasil Memperbarui data ' . $user->name . ' (' . $user->username . ')');
    }

    public function delete($id)
    {
        $user = User::find($id);
        if( $user ) {
            $user->delete();
        }

        return response()->json(['success' => true]);
    }
}
