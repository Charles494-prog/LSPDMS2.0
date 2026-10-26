<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Like the built-in 'encrypted' cast, but a value that can no longer be
 * decrypted (for example after APP_KEY changed) reads as null instead of
 * throwing, so the page that shows it keeps working and the value can be
 * entered again.
 */
class EncryptedOrNull implements CastsAttributes
{
    /** @var array<string, true> attributes already logged during this request */
    private static array $warned = [];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            $id = $model::class.'.'.$key;
            if (! isset(self::$warned[$id])) {
                self::$warned[$id] = true;
                Log::warning('Stored encrypted value could not be decrypted; treating it as empty.', [
                    'model' => $model::class,
                    'attribute' => $key,
                ]);
            }

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString((string) $value);
    }
}
