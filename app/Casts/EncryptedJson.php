<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (is_array($decoded)) {
            return $decoded;
        }

        if (!is_string($decoded) || $decoded === '') {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($decoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return [];
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return json_encode(Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
    }
}
