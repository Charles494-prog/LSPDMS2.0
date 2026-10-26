<?php

namespace App\Models;

use App\Casts\EncryptedOrNull;
use Illuminate\Database\Eloquent\Model;

class SystemSmtpSetting extends Model
{
    /** Gmail SMTP transport used for every saved sender account. */
    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    public const ENCRYPTION = 'tls';

    protected $fillable = [
        'email',
        'app_password',
    ];

    protected function casts(): array
    {
        return [
            // Unreadable after an APP_KEY change: reads as null so the account can be saved again.
            'app_password' => EncryptedOrNull::class,
            'pending_app_password' => EncryptedOrNull::class,
            'pending_saved_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verification_sent_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    /**
     * Non-reversible fingerprint (v2) of a sender account and the transport it
     * is used with. Verification is only valid while host, port, encryption,
     * address and app password are all unchanged since the code was confirmed.
     */
    public static function fingerprintFor(?string $email, ?string $password): string
    {
        return hash_hmac(
            'sha256',
            'v2|'.self::HOST.'|'.self::PORT.'|'.self::ENCRYPTION.'|'.mb_strtolower(trim((string) $email)).'|'.(string) $password,
            (string) config('app.key')
        );
    }

    public function fingerprint(): string
    {
        return self::fingerprintFor($this->email, $this->app_password);
    }

    /** Fingerprint of the pending (unconfirmed) account, or null if there is none. */
    public function pendingFingerprint(): ?string
    {
        return $this->hasPendingAccount()
            ? self::fingerprintFor($this->pending_email, $this->pending_app_password)
            : null;
    }

    /** The pre-v2 fingerprint: HMAC(email|app_password). Only read, never written. */
    public function legacyFingerprint(): string
    {
        return hash_hmac('sha256', strtolower((string) $this->email).'|'.(string) $this->app_password, (string) config('app.key'));
    }

    public function hasPendingAccount(): bool
    {
        return filled($this->pending_email) && filled($this->pending_app_password);
    }

    /**
     * The active account is verified. A legacy v1 fingerprint is accepted once
     * and transparently upgraded to v2, so an account verified before this
     * change stays verified.
     */
    public function isVerified(): bool
    {
        if ($this->verified_at === null || blank($this->verified_fingerprint) || blank($this->email)) {
            return false;
        }

        $stored = (string) $this->verified_fingerprint;
        if (hash_equals($stored, $this->fingerprint())) {
            return true;
        }

        if (! hash_equals($stored, $this->legacyFingerprint())) {
            return false;
        }

        if ($this->exists) {
            static::query()
                ->whereKey($this->getKey())
                ->where('verified_fingerprint', $stored)
                ->update(['verified_fingerprint' => $this->fingerprint()]);
        }
        $this->verified_fingerprint = $this->fingerprint();
        $this->syncOriginalAttribute('verified_fingerprint');

        return true;
    }

    /** Verified once, but the account/transport no longer matches the fingerprint. */
    public function verificationWasInvalidated(): bool
    {
        return $this->verified_at !== null && ! $this->isVerified();
    }
}
