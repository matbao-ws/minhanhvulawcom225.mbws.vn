<?php

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Which files belong to core — the code Mắt Bão ships to every website and a core update
 * replaces — and which belong to the website (its theme, its settings, its data).
 *
 * An ALLOWLIST: only paths matched by CORE are core. Anything else — the theme under
 * resources/views/client, public/client-assets other than the editor's vendor files,
 * routes/theme.php, .env, storage, uploads, and any file nobody listed — is the website's
 * and an update never writes it. Adding a new core directory means adding it here.
 *
 * Dependencies (vendor/) ship as their own artifact, only when composer.lock changes.
 *
 * The checksums recorded when a release is installed let `core:status` (and the updater)
 * find core files edited by hand on a website, so an update stops instead of overwriting them.
 */
class CoreManifest
{
    /** `dir/**` = everything under dir; anything else = that exact file. */
    public const CORE = [
        'app/**',
        'bootstrap/app.php',
        'bootstrap/providers.php',
        'config/**',
        'database/factories/**',
        'database/migrations/**',
        'database/seeders/**',
        'lang/**',
        'routes/admin.php',
        'routes/api.php',
        'routes/client.php',
        'routes/console.php',
        'routes/web.php',
        'resources/views/admin/**',
        'resources/views/api/**',
        'resources/views/auth/**',
        'resources/views/components/**',
        'resources/views/core/**',
        'resources/views/emails/**',
        'resources/views/errors/**',
        'resources/views/vendor/**',
        'resources/views/vnpay_mock.blade.php',
        'public/admin-assets/**',
        'public/client-assets/vendor/**',
        'public/docs/**',
        'public/index.php',
        'artisan',
        'composer.json',
        'composer.lock',
        'VERSION',
        // The working instructions AI agents follow on every website, and the documents they
        // point to. They describe core behaviour — the editable audit, the markup contract, the
        // update rules — so they have to move with the core they describe; a site keeping last
        // year's contract would build by rules the code no longer enforces.
        // Root files are listed one by one, never as `*.md`: a core file *added* on a website
        // is drift and blocks the next update, so a glob would turn any note a developer drops
        // at the root into a stuck site. Project notes belong outside these paths.
        '.agents/skills/**',
        'AGENTS.md',
        'AI_BUILD_PROMPT.md',
        'CLAUDE.md',
        'GEMINI.md',
        'README.md',
        'docs/**',
    ];

    public const DEPENDENCIES = ['vendor/**'];

    /**
     * Never core, whatever CORE says — in this version or in a release's own list. A hard deny:
     * the updater refuses a release that would write any of these.
     */
    public const SITE_OWNED = [
        'resources/views/client/**',
        'public/client-assets/css/**',
        'routes/theme.php',
        'theme/**',
        'extensions/**',
        '.env',
        '.htaccess',
        'public/.htaccess',
        'public/robots.txt',
        'public/storage/**',
        'storage/**',
    ];

    /** Files never recorded even inside a core directory. */
    private const IGNORED_NAMES = ['.DS_Store', 'Thumbs.db', '.gitkeep'];

    private readonly string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim(str_replace('\\', '/', $base ?? base_path()), '/');
    }

    public function base(): string
    {
        return $this->base;
    }

    public function version(): string
    {
        $file = $this->base.'/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    /** core | dependencies | site */
    public function owner(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return match (true) {
            self::matches($path, self::SITE_OWNED) => 'site',
            self::matches($path, self::CORE) => 'core',
            self::matches($path, self::DEPENDENCIES) => 'dependencies',
            default => 'site',
        };
    }

    /** @return list<string> relative paths of the core files present, sorted */
    public function coreFiles(): array
    {
        $files = [];
        foreach (self::CORE as $pattern) {
            if (str_ends_with($pattern, '/**')) {
                $dir = $this->base.'/'.substr($pattern, 0, -3);
                if (! is_dir($dir)) {
                    continue;
                }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isFile() && ! in_array($file->getFilename(), self::IGNORED_NAMES, true)) {
                        $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->base) + 1);
                    }
                }
            } elseif (is_file($this->base.'/'.$pattern)) {
                $files[] = $pattern;
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return array<string, string> path => sha256 of every core file present */
    public function hashes(): array
    {
        $hashes = [];
        foreach ($this->coreFiles() as $path) {
            $hashes[$path] = hash_file('sha256', $this->base.'/'.$path);
        }

        return $hashes;
    }

    /** @return array{version: string, generated_at: string, files: array<string, string>} */
    public function build(): array
    {
        return ['version' => $this->version(), 'generated_at' => now()->toIso8601String(), 'files' => $this->hashes()];
    }

    /** Where the checksums of the installed release are kept on a website. */
    public function installedPath(): string
    {
        return $this->base.'/storage/app/core/installed.json';
    }

    /** @return array{version: string, files: array<string, string>}|null */
    public function installed(): ?array
    {
        $path = $this->installedPath();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) && is_array($data['files'] ?? null) ? $data : null;
    }

    /** Record the current core files as the installed release (first adoption, or after an install). */
    public function recordInstalled(): array
    {
        $manifest = $this->build();
        $this->writeInstalled($manifest);

        return $manifest;
    }

    /** @param array{version: string, files: array<string, string>} $manifest */
    public function writeInstalled(array $manifest): void
    {
        if (! is_dir(dirname($this->installedPath()))) {
            mkdir(dirname($this->installedPath()), 0775, true);
        }
        $manifest += ['generated_at' => now()->toIso8601String()];
        file_put_contents($this->installedPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Core files that differ from the installed release.
     *
     * @return array{modified: list<string>, missing: list<string>, added: list<string>}|null null when nothing was recorded yet
     */
    public function drift(): ?array
    {
        $installed = $this->installed();
        if ($installed === null) {
            return null;
        }

        $current = $this->hashes();
        $recorded = $installed['files'];

        return [
            'modified' => array_values(array_keys(array_filter($current, fn ($hash, $path) => isset($recorded[$path]) && $recorded[$path] !== $hash, ARRAY_FILTER_USE_BOTH))),
            'missing' => array_values(array_diff(array_keys($recorded), array_keys($current))),
            'added' => array_values(array_diff(array_keys($current), array_keys($recorded))),
        ];
    }

    /** `dir/**` = everything under dir; anything else = that exact path. */
    public static function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '/**') ? str_starts_with($path, substr($pattern, 0, -2)) : $path === $pattern) {
                return true;
            }
        }

        return false;
    }
}
