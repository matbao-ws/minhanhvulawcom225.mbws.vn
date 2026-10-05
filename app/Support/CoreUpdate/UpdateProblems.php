<?php

namespace App\Support\CoreUpdate;

/**
 * Turns the updater's technical reasons ("drift: 3 core file(s) …", "migrate: artisan migrate
 * failed …", "download: …") into what the update page tells an admin: a plain sentence and a short
 * code to give Mat Bao. The original text stays available as the technical detail.
 */
class UpdateProblems
{
    /** Reason prefixes the updater and the update service write, in the order they are checked. */
    private const CODES = [
        'locked', 'interrupted', 'not_newer', 'min_from', 'php', 'no_baseline', 'drift', 'backup_required',
        'dependencies_missing', 'not_writable', 'disk_space', 'extensions', 'invalid_release', 'download',
        'gateway', 'not_offered',
    ];

    /** Steps of an install: a failure there was rolled back to the previous code. */
    private const INSTALL_STEPS = ['prepare', 'backup', 'stage', 'maintenance', 'swap', 'package:discover', 'migrate', 'optimize:clear', 'optimize', 'health', 'rollback'];

    /**
     * @return list<array{code: string, message: string, detail: string}>
     */
    public static function explain(?string $error): array
    {
        if ($error === null || trim($error) === '') {
            return [];
        }

        $problems = [];
        foreach (array_filter(array_map('trim', explode(' | ', $error))) as $part) {
            $prefix = strtolower(trim((string) strstr($part, ':', true)));
            $code = match (true) {
                in_array($prefix, self::CODES, true) => $prefix,
                in_array($prefix, self::INSTALL_STEPS, true) => 'install_failed',
                str_contains($part, 'no longer offered') => 'not_offered',
                default => 'unknown',
            };
            $problems[] = [
                'code' => strtoupper($code),
                'message' => __('admin.core_update.problems.'.$code, ['step' => __('admin.core_update.steps.'.self::stepGroup($prefix))]),
                'detail' => $part,
            ];
        }

        return array_values(array_unique($problems, SORT_REGULAR));
    }

    /** Which of the page's progress steps an updater step belongs to. */
    public static function stepGroup(?string $step): string
    {
        return match (true) {
            in_array($step, ['download', 'gateway', 'offer'], true) => 'download',
            in_array($step, ['prepare', 'backup', 'stage'], true) => 'prepare',
            in_array($step, ['maintenance', 'swap'], true) => 'install',
            in_array($step, ['package:discover', 'migrate', 'optimize:clear', 'optimize'], true) => 'migrate',
            $step === 'health' => 'health',
            default => 'install',
        };
    }
}
