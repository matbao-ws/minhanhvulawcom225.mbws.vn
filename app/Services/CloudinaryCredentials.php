<?php

namespace App\Services;

use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudinaryCredentials
{
    public const SETTING_KEY = 'cloudinary_credentials';

    private bool $resolved = false;

    /** @var array{cloud_name: string, api_key: string, api_secret: string}|null */
    private ?array $credentials = null;

    private string $source = 'local';

    /** @return array{cloud_name: string, api_key: string, api_secret: string}|null */
    public function current(): ?array
    {
        if ($this->resolved) {
            return $this->credentials;
        }

        $this->resolved = true;
        $stored = ProjectSetting::query()
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if ($stored !== null) {
            $value = $stored->setting_value;
            if (is_array($value) && ($value['enabled'] ?? true) !== false && $this->isComplete($value)) {
                $this->source = 'database';
                $this->credentials = $this->onlyCredentials($value);
            } else {
                // The presence of a disabled database record deliberately
                // overrides environment credentials after an administrator
                // presses "Disconnect".
                $this->source = 'disabled';
            }

            return $this->credentials;
        }

        $environment = [
            'cloud_name' => (string) config('services.cloudinary.cloud_name', ''),
            'api_key' => (string) config('services.cloudinary.api_key', ''),
            'api_secret' => (string) config('services.cloudinary.api_secret', ''),
        ];

        if ($this->isComplete($environment)) {
            $this->source = 'environment';
            $this->credentials = $environment;
        }

        return $this->credentials;
    }

    /**
     * @return array{configured: bool, source: string, cloud_name: string|null,
     *     api_key: string|null, api_secret: string|null}
     *
     * api_key/api_secret are masked, never the real value: this is rendered
     * straight into the admin page so an admin can recognise which credential
     * is active, and the real secret must never reach the browser once it is
     * stored encrypted.
     */
    public function summary(): array
    {
        $credentials = $this->current();

        return [
            'configured' => $credentials !== null,
            'source' => $this->source,
            'cloud_name' => $credentials['cloud_name'] ?? null,
            'api_key' => isset($credentials['api_key']) ? $this->mask($credentials['api_key']) : null,
            'api_secret' => isset($credentials['api_secret']) ? $this->mask($credentials['api_secret']) : null,
        ];
    }

    /**
     * Show enough of a credential to recognise it without exposing the value:
     * short secrets are masked completely rather than partially, since showing
     * 4+4 characters of an 8-character value leaves almost nothing hidden.
     */
    private function mask(string $value): string
    {
        $length = strlen($value);

        return $length <= 8
            ? str_repeat('*', $length)
            : substr($value, 0, 4).'****'.substr($value, -4);
    }

    /** @param array{cloud_name: string, api_key: string, api_secret: string} $credentials */
    public function verify(array $credentials): bool
    {
        try {
            $response = Http::acceptJson()
                ->withBasicAuth($credentials['api_key'], $credentials['api_secret'])
                ->connectTimeout(4)
                ->timeout(10)
                ->get('https://api.cloudinary.com/v1_1/'.rawurlencode($credentials['cloud_name']).'/ping');

            return $response->successful() && $response->json('status') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{cloud_name: string, api_key: string, api_secret: string}  $credentials
     */
    public function connect(array $credentials): ProjectSetting
    {
        $previousCloudName = $this->current()['cloud_name'] ?? null;
        $record = ProjectSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            [
                'setting_value' => ['enabled' => true, ...$credentials],
                'updated_at' => now(),
            ],
        );

        $this->forgetUsage($previousCloudName);
        $this->forgetUsage($credentials['cloud_name']);
        $this->forgetResolved();

        return $record;
    }

    public function disconnect(): ProjectSetting
    {
        $previousCloudName = $this->current()['cloud_name'] ?? null;
        $record = ProjectSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            [
                // Replacing the complete encrypted payload removes the old
                // key and secret instead of leaving dormant credentials behind.
                'setting_value' => ['enabled' => false],
                'updated_at' => now(),
            ],
        );

        $this->forgetUsage($previousCloudName);
        $this->forgetResolved();

        return $record;
    }

    public function usageCacheKey(?string $cloudName = null): ?string
    {
        $cloudName ??= $this->current()['cloud_name'] ?? null;

        return $cloudName === null
            ? null
            : 'cloudinary:usage:'.hash('sha256', $cloudName);
    }

    private function forgetUsage(?string $cloudName): void
    {
        $key = $cloudName === null ? null : $this->usageCacheKey($cloudName);
        if ($key !== null) {
            Cache::forget($key);
        }
    }

    private function forgetResolved(): void
    {
        $this->resolved = false;
        $this->credentials = null;
        $this->source = 'local';
    }

    /** @param array<string, mixed> $value */
    private function isComplete(array $value): bool
    {
        return filled($value['cloud_name'] ?? null)
            && filled($value['api_key'] ?? null)
            && filled($value['api_secret'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{cloud_name: string, api_key: string, api_secret: string}
     */
    private function onlyCredentials(array $value): array
    {
        return [
            'cloud_name' => (string) $value['cloud_name'],
            'api_key' => (string) $value['api_key'],
            'api_secret' => (string) $value['api_secret'],
        ];
    }
}
