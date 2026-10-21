<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings', [
            'user' => $request->session()->get('login_user'),
        ]);
    }

    public function update(Request $request)
    {
        $sessionUser = $request->session()->get('login_user');
        $validated = $request->validate([
            'dark_mode' => ['required', 'boolean'],
        ]);
        $darkMode = (bool) $validated['dark_mode'];

        DB::table('login_user')->where('id', $sessionUser['id'])->update([
            'dark_mode' => $darkMode,
        ]);

        $sessionUser['dark_mode'] = $darkMode;
        $request->session()->put('login_user', $sessionUser);

        if ($request->expectsJson()) {
            return response()->json(['dark_mode' => $darkMode]);
        }

        return back()->with('status', 'Settings saved.');
    }
}