<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('register');
    }

    public function store(Request $request)
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (DB::table('login_user')->whereRaw('LOWER(TRIM(gmail)) = ?', [$value])->exists()) {
                        $fail('An account with this email address already exists.');
                    }
                },
            ],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ]);

        try {
            User::query()->create([
                'first_name' => trim($validated['first_name']),
                'last_name' => trim($validated['last_name']),
                'gmail' => $validated['email'],
                'password' => $validated['password'],
                'tungkulin' => 'Secretary',
                'is_verified' => false,
                'dashboard_allow' => false,
                'form_generator_allow' => false,
                'Delistment_allow' => false,
                'Inquire_Allow' => false,
                'tarheta_status_allow' => false,
                'filter_bnh_helper_allow' => false,
                'chatbot_records_allow' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two registrations for the same email raced past the check above.
            throw ValidationException::withMessages(['email' => 'An account with this email address already exists.']);
        }

        return redirect()
            ->route('login.form')
            ->with('status', 'Registration submitted. The Head Secretary must grant your account access before you can sign in.');
    }
}
