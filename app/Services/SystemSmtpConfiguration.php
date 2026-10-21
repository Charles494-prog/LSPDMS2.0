<?php

namespace App\Services;

use App\Models\SystemSmtpSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SystemSmtpConfiguration
{
    public function isConfigured(): bool
    {
        if ($this->databaseSettingsConfigured()) {
            return true;
        }

        return $this->isEnvironmentConfigured();
    }

    public function isEnvironmentConfigured(): bool
    {
        return config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.transport') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && filled(config('mail.mailers.smtp.port'));
    }

    private function databaseSettingsConfigured(): bool
    {
        if (! Schema::hasTable('system_smtp_settings')) {
            return false;
        }

        $settings = SystemSmtpSetting::query()->first();

        return $settings !== null && filled($settings->email) && filled($settings->app_password);
    }

    public function apply(): void
    {
        if (! Schema::hasTable('system_smtp_settings')) {
            return;
        }

        $settings = SystemSmtpSetting::query()->first();
        if (! $settings || blank($settings->email) || blank($settings->app_password)) {
            return;
        }

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => 'tls',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => $settings->email,
            'mail.mailers.smtp.password' => $settings->app_password,
            'mail.from.address' => $settings->email,
        ]);
        Mail::purge('smtp');
    }
}
