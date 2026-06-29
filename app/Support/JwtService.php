<?php

namespace App\Support;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private static function getKey(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        return $key;
    }

    public static function generateToken(User $user, int $expirationSeconds = 86400): string
    {
        $key = self::getKey();
        $payload = [
            'iss' => config('app.url', 'http://localhost'),
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => time(),
            'exp' => time() + $expirationSeconds,
        ];

        return JWT::encode($payload, $key, 'HS256');
    }

    public static function decode(string $token): ?array
    {
        try {
            $key = self::getKey();
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
