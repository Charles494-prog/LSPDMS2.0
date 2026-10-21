<?php

namespace App\Http\Controllers;

use App\Models\SystemSmtpSetting;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SystemSmtpSettingsController extends Controller
{
    public function update(Request $request, SystemSmtpConfiguration $configuration): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'app_password' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = SystemSmtpSetting::query()->first() ?? new SystemSmtpSetting;
        $appPassword = preg_replace('/\s+/', '', trim((string) ($validated['app_password'] ?? '')));

        $emailChanged = strcasecmp((string) $settings->email, $validated['email']) !== 0;
        if ($appPassword === '' && ($emailChanged || blank($settings->getRawOriginal('app_password')))) {
            throw ValidationException::withMessages([
                'app_password' => 'Enter a Google App Password when enabling SMTP or changing the Gmail address.',
            ]);
        }

        $settings->email = $validated['email'];
        if ($appPassword !== '') {
            $settings->app_password = $appPassword;
        }
        $settings->save();

        $configuration->apply();

        return response()->json([
            'message' => 'SMTP configuration saved. Email delivery will use this Gmail account.',
            'email' => $settings->email,
            'password_configured' => true,
        ]);
    }
}
