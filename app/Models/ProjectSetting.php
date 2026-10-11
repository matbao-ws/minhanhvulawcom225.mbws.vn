<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ProjectSetting extends Model
{
    private const ENCRYPTED_SETTING_KEYS = [
        'notification_settings',
        'cloudinary_credentials',
    ];

    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'updated_at',
    ];

    protected $casts = ['updated_at' => 'datetime'];

    protected function settingValue(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): mixed {
                if ($value === null) {
                    return null;
                }

                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (! in_array($attributes['setting_key'] ?? null, self::ENCRYPTED_SETTING_KEYS, true)) {
                    return $decoded;
                }

                if (is_array($decoded)) {
                    return $decoded;
                }

                try {
                    return is_string($decoded)
                        ? json_decode(Crypt::decryptString($decoded), true, 512, JSON_THROW_ON_ERROR)
                        : [];
                } catch (DecryptException|\JsonException) {
                    return [];
                }
            },
            set: function (mixed $value, array $attributes): mixed {
                $encoded = json_encode($value, JSON_THROW_ON_ERROR);

                if (in_array($attributes['setting_key'] ?? null, self::ENCRYPTED_SETTING_KEYS, true)) {
                    return json_encode(Crypt::encryptString($encoded), JSON_THROW_ON_ERROR);
                }

                return $encoded;
            },
        );
    }
}
