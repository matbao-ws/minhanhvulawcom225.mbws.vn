<?php

namespace App\Support;

/**
 * Integration settings pages are open to read-only staff (`*.view`), but the
 * secrets on them are not read-only information: a SePay or carrier webhook
 * token lets whoever holds it mark orders paid or delivered. Staff who may
 * change a secret see it (they have to paste it into the partner's dashboard);
 * everyone else gets a placeholder.
 */
final class SecretMask
{
    public const PLACEHOLDER = '••••••••';

    /**
     * @param  array<string, mixed>|null  $settings
     * @param  list<string>  $paths  dot paths of the secret values
     * @return array<string, mixed>
     */
    public static function apply(?array $settings, array $paths): array
    {
        $settings ??= [];
        foreach ($paths as $path) {
            if (filled(data_get($settings, $path))) {
                data_set($settings, $path, self::PLACEHOLDER);
            }
        }

        return $settings;
    }
}
