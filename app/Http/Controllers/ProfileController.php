<?php

namespace App\Http\Controllers;

use App\Services\FileStore;
use App\Support\PasswordVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', [
            'user' => $request->session()->get('login_user'),
        ]);
    }

    public function update(Request $request)
    {
        $sessionUser = $request->session()->get('login_user');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'picture' => ['nullable', 'image', 'max:2048'],
        ]);

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
        ];

        $files = app(FileStore::class);
        $previousPicture = (string) (DB::table('login_user')->where('id', $sessionUser['id'])->value('profile_picture') ?? '');
        $newPicture = null;
        if ($request->hasFile('picture')) {
            // Kept on the public disk and in stored_files so SQL exports carry the picture.
            $newPicture = $files->putUploadedFile('public', 'profile-pictures', $request->file('picture'));
            $updateData['profile_picture'] = $newPicture;
        }

        try {
            DB::table('login_user')->where('id', $sessionUser['id'])->update($updateData);
        } catch (\Throwable $exception) {
            if ($newPicture !== null) {
                $files->delete('public', $newPicture);
            }
            throw $exception;
        }

        if ($newPicture !== null && $previousPicture !== '' && $previousPicture !== $newPicture
            && str_starts_with($previousPicture, 'profile-pictures/')) {
            $files->delete('public', $previousPicture);
        }

        $sessionUser = array_merge($sessionUser, $updateData);
        $request->session()->put('login_user', $sessionUser);

        return back()->with('status', 'Profile updated.');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ]);

        $userId = (int) $request->session()->get('login_user.id');
        $user = DB::table('login_user')->where('id', $userId)->first();
        abort_unless($user, 403);

        $matches = PasswordVerifier::check($validated['current_password'], $user->password);
        if (! $matches) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        DB::table('login_user')->where('id', $userId)->update([
            'password' => Hash::make($validated['password']),
            'auth_version' => DB::raw('auth_version + 1'),
            'auth_notice' => 'password_changed',
            'remember_token' => null,
            'remember_token_expires_at' => null,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Change of Password Detected, Please Login Again.');
    }
}