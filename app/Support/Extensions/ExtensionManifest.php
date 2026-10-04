<?php

namespace App\Support\Extensions;

use App\Support\CoreUpdate\Semver;

/**
 * extensions/<id>/extension.json, validated (docs/EXTENSIONS_DESIGN.md, section 1). Everything an
 * extension names — PHP namespace, table prefix, permission and menu keys, view namespace, route
 * names — is derived from `id`, never from the display name.
 */
final class ExtensionManifest
{
    public const SCHEMA = 1;

    public const ID_PATTERN = '/^[a-z][a-z0-9]{1,30}$/';

    /** Names the core or Laravel already uses as view/lang/config namespaces or URL segments. */
    public const RESERVED_IDS = [
        'admin', 'api', 'app', 'auth', 'client', 'core', 'custom', 'emails', 'enabled', 'errors', 'ext', 'extension',
        'extensions', 'laravel', 'mail', 'notifications', 'pagination', 'theme', 'vendor', 'validation',
    ];

    /**
     * @param  list<array{code: string, label: string}>  $permissions
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $requiresCore,
        public readonly string $provider,
        public readonly array $permissions,
        public readonly ?string $description,
        public readonly ?string $author,
        public readonly string $path,
    ) {}

    /**
     * @throws InvalidExtension listing every problem found
     */
    public static function fromDirectory(string $dir): self
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $folder = basename($dir);
        $file = $dir.'/extension.json';

        if (! is_file($file)) {
            throw new InvalidExtension('extension.json is missing');
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            throw new InvalidExtension('extension.json is not a JSON object');
        }

        $problems = [];
        $string = fn (string $key) => is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        if (($data['schema'] ?? null) !== self::SCHEMA) {
            $problems[] = 'schema must be '.self::SCHEMA;
        }

        $id = $string('id');
        if (! preg_match(self::ID_PATTERN, $id)) {
            $problems[] = 'id must match '.self::ID_PATTERN.' (lowercase letters and digits)';
        } elseif (in_array($id, self::RESERVED_IDS, true)) {
            $problems[] = "id \"{$id}\" is reserved";
        }
        if ($id !== $folder) {
            $problems[] = "id \"{$id}\" must equal the folder name \"{$folder}\"";
        }

        foreach (['name', 'version', 'requires_core', 'provider'] as $key) {
            if ($string($key) === '') {
                $problems[] = "{$key} is required";
            }
        }
        if ($string('version') !== '' && ! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $string('version'))) {
            $problems[] = 'version must be semver (1.0.0)';
        }
        if ($string('requires_core') !== '' && ! Semver::isConstraint($string('requires_core'))) {
            $problems[] = 'requires_core is not a version constraint (e.g. >=1.2.0 <2.0.0)';
        }

        $namespace = 'Extensions\\'.ucfirst($id).'\\';
        $provider = ltrim($string('provider'), '\\');
        if ($provider !== '' && (! str_starts_with($provider, $namespace) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $provider))) {
            $problems[] = "provider must be a class in {$namespace}";
        }

        $permissions = [];
        $declared = $data['permissions'] ?? [];
        if (! is_array($declared) || ! array_is_list($declared)) {
            $problems[] = 'permissions must be a list';
            $declared = [];
        }
        foreach ($declared as $i => $permission) {
            $code = is_array($permission) && is_string($permission['code'] ?? null) ? $permission['code'] : '';
            $label = is_array($permission) && is_string($permission['label'] ?? null) ? trim($permission['label']) : '';
            if (! preg_match('/^'.preg_quote($id, '/').'\.[a-z][a-z0-9_]*$/', $code)) {
                $problems[] = "permissions[{$i}].code must be \"{$id}.<action>\"";
            } elseif (isset($permissions[$code])) {
                $problems[] = "permissions[{$i}].code \"{$code}\" is declared twice";
            } elseif ($label === '') {
                $problems[] = "permissions[{$i}].label is required";
            } else {
                $permissions[$code] = ['code' => $code, 'label' => $label];
            }
        }

        if ($problems !== []) {
            throw new InvalidExtension(implode('; ', $problems));
        }

        return new self(
            id: $id,
            name: $string('name'),
            version: $string('version'),
            requiresCore: $string('requires_core'),
            provider: $provider,
            permissions: array_values($permissions),
            description: $string('description') ?: null,
            author: $string('author') ?: null,
            path: $dir,
        );
    }

    public function studly(): string
    {
        return ucfirst($this->id);
    }

    /** Extensions\Dealers\ */
    public function namespace(): string
    {
        return 'Extensions\\'.$this->studly().'\\';
    }

    /** ext_dealers_ */
    public function tablePrefix(): string
    {
        return 'ext_'.$this->id.'_';
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path.'/'.ltrim($relative, '/');
    }

    public function supportsCore(string $version): bool
    {
        return Semver::satisfies($version, $this->requiresCore);
    }

    /** @return list<string> */
    public function permissionCodes(): array
    {
        return array_column($this->permissions, 'code');
    }
}
