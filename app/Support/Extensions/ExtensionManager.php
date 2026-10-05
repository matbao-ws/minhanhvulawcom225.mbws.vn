<?php

namespace App\Support\Extensions;

use App\Support\CoreManifest;
use Throwable;

/**
 * Finds the website's extensions and decides which may run (docs/EXTENSIONS_DESIGN.md, section 2):
 *
 *   discover  extensions/<folder>/extension.json — direct child folders only, never a symlink
 *   validate  ExtensionManifest (id = folder, provider in its namespace, ...)
 *   state     switched off in storage/app/core/extensions.json?
 *   compat    requires_core satisfied by this core's VERSION?
 *
 * What passes is "ready"; ExtensionServiceProvider then loads it and reports back "loaded" or
 * "failed". Nothing here runs extension code, so the console commands and the updater can use it
 * while an extension is broken.
 */
class ExtensionManager
{
    public const READY = 'ready';

    public const LOADED = 'loaded';

    public const DISABLED = 'disabled';

    public const INCOMPATIBLE = 'incompatible';

    public const INVALID = 'invalid';

    public const FAILED = 'failed';

    /** @var array<string, array{id: string, manifest: ?ExtensionManifest, status: string, error: ?string}>|null */
    private ?array $records = null;

    public function __construct(
        private readonly string $base,
        private readonly ExtensionState $state,
    ) {}

    /** EXTENSIONS_ENABLED=false: run the bare core, whatever extensions/ holds. */
    public function enabled(): bool
    {
        return (bool) config('extensions.enabled', true);
    }

    public function root(): string
    {
        return rtrim(str_replace('\\', '/', $this->base), '/').'/extensions';
    }

    public function state(): ExtensionState
    {
        return $this->state;
    }

    /**
     * Every extension folder with what became of it, sorted by id.
     *
     * @return array<string, array{id: string, manifest: ?ExtensionManifest, status: string, error: ?string}>
     */
    public function all(): array
    {
        return $this->records ??= $this->discover((new CoreManifest($this->base))->version());
    }

    /** @return list<ExtensionManifest> extensions that passed every check and may be loaded */
    public function ready(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return array_values(array_map(
            fn (array $record) => $record['manifest'],
            array_filter($this->all(), fn (array $record) => $record['status'] === self::READY),
        ));
    }

    /** @return list<ExtensionManifest> extensions running in this process */
    public function loaded(): array
    {
        return array_values(array_map(
            fn (array $record) => $record['manifest'],
            array_filter($this->all(), fn (array $record) => $record['status'] === self::LOADED),
        ));
    }

    public function markLoaded(string $id): void
    {
        $this->all();
        if (($this->records[$id]['status'] ?? null) === self::READY) {
            $this->records[$id]['status'] = self::LOADED;
        }
    }

    public function markFailed(string $id, Throwable|string $error): void
    {
        $this->all();
        if (isset($this->records[$id])) {
            $this->records[$id]['status'] = self::FAILED;
            $this->records[$id]['error'] = $error instanceof Throwable
                ? $error::class.': '.$error->getMessage()
                : $error;
        }
    }

    /**
     * Why an update to $target would break an enabled extension — read straight from the
     * manifests, without loading any extension code (the updater calls this before installing).
     *
     * @return list<array{id: string, name: string, version: string, requires_core: string}>
     */
    public function incompatibleWith(string $target): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $blocking = [];
        foreach ($this->discover($target) as $record) {
            if ($record['status'] === self::INCOMPATIBLE) {
                $manifest = $record['manifest'];
                $blocking[] = ['id' => $manifest->id, 'name' => $manifest->name, 'version' => $manifest->version, 'requires_core' => $manifest->requiresCore];
            }
        }

        return $blocking;
    }

    /** Drop the per-process result (after enable/disable, or in tests). */
    public function forget(): void
    {
        $this->records = null;
    }

    /** @return array<string, array{id: string, manifest: ?ExtensionManifest, status: string, error: ?string}> */
    private function discover(string $coreVersion): array
    {
        $root = $this->root();
        if (! is_dir($root)) {
            return [];
        }

        $records = [];
        foreach (scandir($root) ?: [] as $folder) {
            $dir = $root.'/'.$folder;
            if ($folder === '.' || $folder === '..' || str_starts_with($folder, '.') || ! is_dir($dir)) {
                continue;
            }
            if (is_link($dir)) {
                $records[$folder] = ['id' => $folder, 'manifest' => null, 'status' => self::INVALID, 'error' => 'symlinked folders are not loaded'];

                continue;
            }

            try {
                $manifest = ExtensionManifest::fromDirectory($dir);
            } catch (InvalidExtension $e) {
                $records[$folder] = ['id' => $folder, 'manifest' => null, 'status' => self::INVALID, 'error' => $e->getMessage()];

                continue;
            }

            [$status, $error] = match (true) {
                $this->state->isDisabled($manifest->id) => [self::DISABLED, $this->state->disabled()[$manifest->id]['reason'] ?? null],
                ! $manifest->supportsCore($coreVersion) => [self::INCOMPATIBLE, "requires core {$manifest->requiresCore}, this core is {$coreVersion}"],
                default => [self::READY, null],
            };
            $records[$folder] = ['id' => $folder, 'manifest' => $manifest, 'status' => $status, 'error' => $error];
        }
        ksort($records);

        return $records;
    }
}
