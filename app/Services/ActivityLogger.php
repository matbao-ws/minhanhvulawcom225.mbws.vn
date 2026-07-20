<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'secret',
        'token',
        'api_key',
        'hash_secret',
        'webhook_secret',
        'access_token',
        'refresh_token',
    ];

    public static function log(string $action, ?Model $subject, string $description, array $changes = []): void
    {
        AdminActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'changes' => $changes ? self::redact($changes) : null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (is_array($value)) {
                $values[$key] = self::redact($value);
                continue;
            }

            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($normalizedKey, $sensitiveKey)) {
                    $values[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $values;
    }
}
