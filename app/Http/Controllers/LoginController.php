<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLogin()
    {
        $globalVariables = GlobalVariable::query()->first();

        return view('welcome', [
            'allowForgotPassword' => (bool) ($globalVariables?->allow_forgot_password ?? false),
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return redirect()
                ->route('login.form')
                ->withInput($request->only('email'))
                ->withErrors(['login' => 'Invalid username or password.']);
        }
        $credentials = $validator->validated();

        $email = strtolower(trim($credentials['email']));

        $user = DB::table('login_user')
            ->whereRaw('LOWER(TRIM(gmail)) = ?', [$email])
            ->first();

        $passwordMatches = false;

        if ($user) {
            $storedPassword = (string) $user->password;
            $isHashedPassword = str_starts_with($storedPassword, '$2y$')
                || str_starts_with($storedPassword, '$2a$')
                || str_starts_with($storedPassword, '$2b$')
                || str_starts_with($storedPassword, '$argon2');

            if ($isHashedPassword) {
                $passwordMatches = Hash::check($credentials['password'], $user->password);
            } else {
                $passwordMatches = hash_equals($storedPassword, $credentials['password']);
            }
        }

        if (! $passwordMatches) {
            return redirect()
                ->route('login.form')
                ->withInput($request->only('email'))
                ->withErrors(['login' => 'Invalid username or password.']);
        }

        if ((int) $user->is_verified !== 1) {
            return redirect()
                ->route('login.form')
                ->withInput($request->only('email'))
                ->withErrors(['login' => 'Account has not been granted access by the head secretary.']);
        }

        $globalVariables = GlobalVariable::query()->first();
        if (! (bool) ($globalVariables?->require_otp ?? false)) {
            return $this->completeLogin($request, (int) $user->id, $request->boolean('remember'));
        }

        try {
            $code = $this->sendVerificationCode($user->gmail);
        } catch (\Throwable $exception) {
            Log::error('LSPDMS verification email failed.', [
                'mailer' => config('mail.default'),
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('login.form')
                ->withInput($request->only('email'))
                ->withErrors(['login' => 'Unable to send the verification code. Please check your internet connection and try again.']);
        }

        $request->session()->put('pending_login_user', [
            'id' => $user->id,
            'gmail' => $user->gmail,
            'tungkulin' => $user->tungkulin,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)->timestamp,
            'sent_at' => now()->timestamp,
            'remember' => $request->boolean('remember'),
        ]);

        return redirect('/verify-code')->with('status', 'A verification code was sent to your Gmail.');
    }

    public function showVerificationForm(Request $request)
    {
        abort_unless($request->session()->has('pending_login_user'), 403);

        $pendingUser = $request->session()->get('pending_login_user');

        return view('verify-code', [
            'expiresAt' => $pendingUser['expires_at'],
            'resendAvailableAt' => ($pendingUser['sent_at'] ?? 0) + 60,
        ]);
    }

    public function verifyCode(Request $request)
    {
        $pendingUser = $request->session()->get('pending_login_user');

        if (! $pendingUser) {
            return redirect('/login')->withErrors(['login' => 'Please log in again.']);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($pendingUser['expires_at'] < now()->timestamp) {
            $request->session()->forget('pending_login_user');

            return redirect('/login?expired=1');
        }

        if (! Hash::check($request->code, $pendingUser['code'])) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        $freshUser = DB::table('login_user')->where('id', $pendingUser['id'])->first();
        if (! $freshUser || (int) $freshUser->is_verified !== 1) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Your account access has been disabled. Please contact the head secretary.']);
        }

        return $this->completeLogin($request, (int) $freshUser->id, ! empty($pendingUser['remember']));
    }

    private function completeLogin(Request $request, int $userId, bool $remember)
    {
        $request->session()->regenerate();
        $freshUser = DB::table('login_user')->where('id', $userId)->first();
        if (! $freshUser || (int) $freshUser->is_verified !== 1) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Your account access has been disabled. Please contact the head secretary.']);
        }

        $request->session()->put('login_user', [
            'id' => $freshUser->id,
            'auth_version' => (int) $freshUser->auth_version,
            'gmail' => $freshUser->gmail,
            'tungkulin' => $freshUser->tungkulin,
            'first_name' => $freshUser->first_name,
            'last_name' => $freshUser->last_name,
            'gender' => $freshUser->gender,
            'profile_picture' => $freshUser->profile_picture,
            'dark_mode' => (bool) $freshUser->dark_mode,
            'dashboard_allow' => (bool) $freshUser->dashboard_allow,
            'form_generator_allow' => (bool) $freshUser->form_generator_allow,
            'Delistment_allow' => (bool) $freshUser->Delistment_allow,
            'Inquire_Allow' => (bool) $freshUser->Inquire_Allow,
            'tarheta_status_allow' => (bool) $freshUser->tarheta_status_allow,
            'filter_bnh_helper_allow' => (bool) $freshUser->filter_bnh_helper_allow,
            'chatbot_records_allow' => (bool) ($freshUser->chatbot_records_allow ?? false),
            'is_verified' => (bool) $freshUser->is_verified,
        ]);

        $request->session()->forget('pending_login_user');

        if ($remember) {
            $this->rememberUser($userId);
        } else {
            Cookie::queue(Cookie::forget('remember_token'));
        }

        return redirect('/home')->with('status', 'Login successful.');
    }

    private function rememberUser(int $userId): void
    {
        $token = Str::random(64);

        DB::table('login_user')->where('id', $userId)->update([
            'remember_token' => Hash::make($token),
            'remember_token_expires_at' => now()->addDays(30),
        ]);

        Cookie::queue(Cookie::make(
            'remember_token',
            $userId.'|'.$token,
            60 * 24 * 30,
            null,
            null,
            config('session.secure'),
            true,
            false,
            'Lax'
        ));
    }

    public function resendCode(Request $request)
    {
        $pendingUser = $request->session()->get('pending_login_user');

        if (! $pendingUser) {
            return redirect('/login')->withErrors(['login' => 'Please log in again.']);
        }

        if (($pendingUser['sent_at'] ?? 0) + 60 > now()->timestamp) {
            return back()->withErrors(['code' => 'Please wait before requesting another code.']);
        }

        try {
            $code = $this->sendVerificationCode($pendingUser['gmail']);
        } catch (\Throwable $exception) {
            Log::error('LSPDMS verification email resend failed.', [
                'mailer' => config('mail.default'),
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['code' => 'Unable to send a new verification code.']);
        }

        $pendingUser['code'] = Hash::make($code);
        $pendingUser['expires_at'] = now()->addMinutes(5)->timestamp;
        $pendingUser['sent_at'] = now()->timestamp;
        $request->session()->put('pending_login_user', $pendingUser);

        return back()->with('status', 'A new verification code was sent.');
    }

    private function sendVerificationCode(string $email): string
    {
        $code = (string) random_int(100000, 999999);

        $html = view('emails.verification-code', ['code' => $code])->render();

        app(SystemSmtpConfiguration::class)->apply();
        Mail::html($html, function ($message) use ($email) {
            $message->to($email)->subject('LSPDMS verification code');
        });

        return $code;
    }

    public function logout(Request $request)
    {
        $user = $request->session()->get('login_user');

        if ($user) {
            DB::table('login_user')->where('id', $user['id'])->update([
                'auth_version' => DB::raw('auth_version + 1'),
                'auth_notice' => 'signed_out_elsewhere',
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ]);
        }

        $request->session()->forget('login_user');
        $request->session()->regenerate();

        Cookie::queue(Cookie::forget('remember_token'));

        return redirect('/login')->with('status', 'You have been logged out.');
    }
}
