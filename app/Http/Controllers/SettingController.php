<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
        $settings = Setting::get();
        return view('setting.list', compact('settings'));
    }

    public function edit($code)
    {
        $setting = Setting::where(['code' => $code])->first();
        if( !$setting ) {
            return redirect( route('setting.list') )->withErrors([$code => 'Tidak Ada']);
        }
        
        return view('setting.form', compact('setting'));
    }

    public function update($code)
    {
        $setting = Setting::where(['code' => $code])->first();
        if( !$setting ) {
            return redirect( route('setting.list') )->withErrors([$code => 'Tidak Ada']);
        }
        $this->request->validate([
            'content' => ['required'],
        ]);


        $content = $this->request->content;
        $setting->update(['content' => $content]);

        return redirect( route('setting.edit', ['code' => $code]) )->with([$code . 'success' => 'Sukses Mengganti ' . $setting->name]);
    }
}
