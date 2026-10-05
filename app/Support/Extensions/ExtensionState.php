<?php

namespace App\Support\Extensions;

/**
 * Which extensions are switched off, in storage/app/core/extensions.json — a file, not the database,
 * so an extension can be disabled while the database or the extension itself is breaking the site.
 * Absent from the file = enabled. The website owns the file: deploys and core updates never write it.
 */
class ExtensionState
{
    public function __construct(private readonly string $base) {}

    public function path(): string
    {
        return rtrim(str_replace('\\', '/', $this->base), '/').'/storage/app/core/extensions.json';
    }

    /** @return array<string, array{at: string, by: string, reason: ?string}> */
    public function disabled(): array
    {
        $data = is_file($this->path()) ? json_decode((string) file_get_contents($this->path()), true) : null;

        return is_array($data['disabled'] ?? null) ? $data['disabled'] : [];
    }

    public function isDisabled(string $id): bool
    {
        return array_key_exists($id, $this->disabled());
    }

    public function disable(string $id, ?string $reason = null, string $by = 'cli'): void
    {
        $disabled = $this->disabled();
        $disabled[$id] = ['at' => now()->toIso8601String(), 'by' => $by, 'reason' => $reason];
        $this->write($disabled);
    }

    public function enable(string $id): void
    {
        $disabled = $this->disabled();
        unset($disabled[$id]);
        $this->write($disabled);
    }

    /** @param  array<string, mixed>  $disabled */
    private function write(array $disabled): void
    {
        $path = $this->path();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode(['disabled' => (object) $disabled], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        rename($tmp, $path);
    }
}
