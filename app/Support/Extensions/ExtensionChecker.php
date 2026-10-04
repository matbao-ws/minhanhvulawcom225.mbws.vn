<?php

namespace App\Support\Extensions;

use App\Support\MigrationSafety;
use Illuminate\Support\Facades\File;

/**
 * Static checks of one extension's code (docs/EXTENSIONS_DESIGN.md, sections 3 and 5) for
 * `php artisan extension:check`, team.py and the project CI. A guardrail against breaking the
 * architecture by mistake — extensions are trusted code, and passing this is not a sandbox.
 *
 *   - only the public extension API of the core is used (App\Extension\*, the read-only models);
 *   - no container binding replaces a core class (bind/singleton/extend/... of App\...);
 *   - migrations touch their own ext_<id>_ tables only and follow expand/contract.
 */
class ExtensionChecker
{
    /** Core classes an extension may use, besides everything under App\Extension\. */
    public const ALLOWED_MODELS = [
        'App\\Models\\Order', 'App\\Models\\OrderItem', 'App\\Models\\Product', 'App\\Models\\ProductVariant',
        'App\\Models\\Category', 'App\\Models\\Brand', 'App\\Models\\Post', 'App\\Models\\User',
    ];

    private const REPLACING_METHODS = ['bind', 'bindif', 'singleton', 'singletonif', 'scoped', 'scopedif', 'instance', 'extend', 'alias'];

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function check(ExtensionManifest $manifest, string $coreVersion): array
    {
        $errors = [];
        $warnings = [];

        if (! $manifest->supportsCore($coreVersion)) {
            $errors[] = "requires core {$manifest->requiresCore}, this core is {$coreVersion}";
        }
        $providerFile = $manifest->path('src/'.str_replace('\\', '/', substr($manifest->provider, strlen($manifest->namespace()))).'.php');
        if (! is_file($providerFile)) {
            $errors[] = 'provider file missing: '.$this->relative($manifest, $providerFile);
        }

        foreach ($this->files($manifest, ['src', 'routes', 'database'], '.php') as $file) {
            foreach ($this->scanPhp((string) file_get_contents($file)) as $problem) {
                $errors[] = $this->relative($manifest, $file).': '.$problem;
            }
        }
        foreach ($this->files($manifest, ['views'], '.php') as $file) {
            foreach ($this->scanTemplate((string) file_get_contents($file)) as $problem) {
                $errors[] = $this->relative($manifest, $file).': '.$problem;
            }
        }

        foreach ($this->files($manifest, ['database/migrations'], '.php') as $file) {
            $name = $this->relative($manifest, $file);
            foreach (MigrationSafety::foreignTables($file, $manifest->tablePrefix()) as $problem) {
                $errors[] = "{$name}: {$problem}";
            }
            foreach (MigrationSafety::violations($file) as $problem) {
                $errors[] = "{$name}: {$problem} (mark it ".MigrationSafety::CONTRACT_MARKER.' only in a contract release)';
            }
            if (MigrationSafety::cascades($file)) {
                $warnings[] = "{$name}: a foreign key deletes in cascade — towards core tables prefer restrictOnDelete() or nullOnDelete()";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @return list<string> */
    public function scanPhp(string $code): array
    {
        $tokens = token_get_all($code);
        $problems = [];
        $imports = [];

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                continue;
            }

            // use App\X\Y [as Z];
            if ($token[0] === T_USE) {
                $j = $this->next($tokens, $i);
                if ($j !== null && in_array($tokens[$j][0] ?? null, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    $name = ltrim($tokens[$j][1], '\\');
                    $k = $this->next($tokens, $j);
                    if ($k !== null && ($tokens[$k] === '{' || ($tokens[$k][0] ?? null) === T_NS_SEPARATOR)) {
                        if ($this->isCore($name)) {
                            $problems[] = "group use of {$name}\\{...}: import core classes one by one";
                        }

                        continue;
                    }
                    $alias = substr($name, (int) strrpos('\\'.$name, '\\'));
                    if ($k !== null && ($tokens[$k][0] ?? null) === T_AS) {
                        $a = $this->next($tokens, $k);
                        $alias = $a !== null ? $tokens[$a][1] : $alias;
                    }
                    $imports[strtolower($alias)] = $name;
                    if ($this->isCore($name) && ! $this->allowed($name)) {
                        $problems[] = "uses {$name}, which is not part of the extension API";
                    }
                    $i = $j;
                }

                continue;
            }

            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                if ($this->isCore($name) && ! $this->allowed($name)) {
                    $problems[] = "uses {$name}, which is not part of the extension API";
                }

                continue;
            }

            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $name = ltrim($this->unquote($token[1]), '\\');
                if (preg_match('/^App\\\\[A-Za-z0-9_\\\\]+$/', $name) && ! $this->allowed($name)) {
                    $problems[] = "names {$name}, which is not part of the extension API";
                }

                continue;
            }

            // ->bind(X::class, ...), App::singleton('App\...'), $this->app->extend(...)
            if ($token[0] === T_STRING && in_array(strtolower($token[1]), self::REPLACING_METHODS, true)) {
                $before = $this->previous($tokens, $i);
                if ($before === null || ! in_array($tokens[$before][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                    continue;
                }
                $open = $this->next($tokens, $i);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $target = $this->firstArgument($tokens, $open, $imports);
                if ($target !== null && $this->isCore($target)) {
                    $problems[] = "{$token[1]}({$target}) replaces a core class: extensions add, listen and register, never replace";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /** Blade: PHP inside views is only reachable as text. */
    public function scanTemplate(string $code): array
    {
        $problems = [];
        preg_match_all('/\\\\?\bApp\\\\[A-Za-z0-9_\\\\]+/', $code, $matches);
        foreach (array_unique($matches[0]) as $name) {
            $name = ltrim($name, '\\');
            if (! $this->allowed($name)) {
                $problems[] = "uses {$name}, which is not part of the extension API";
            }
        }

        return $problems;
    }

    /** A PHP string literal's value: 'App\X' keeps its single backslashes, "App\\X" loses the doubling. */
    private function unquote(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return $literal[0] === "'"
            ? str_replace(['\\\\', "\\'"], ['\\', "'"], $inner)
            : str_replace(['\\\\', '\\"'], ['\\', '"'], $inner);
    }

    private function isCore(string $name): bool
    {
        return str_starts_with($name, 'App\\') || $name === 'App';
    }

    private function allowed(string $name): bool
    {
        return str_starts_with($name, 'App\\Extension\\') || in_array($name, self::ALLOWED_MODELS, true);
    }

    /** The class an argument list starts with: X::class (resolved through use), 'App\X' or a qualified name. */
    private function firstArgument(array $tokens, int $open, array $imports): ?string
    {
        $first = $this->next($tokens, $open);
        if ($first === null || ! is_array($tokens[$first])) {
            return null;
        }
        [$type, $text] = $tokens[$first];

        if ($type === T_CONSTANT_ENCAPSED_STRING) {
            return ltrim($this->unquote($text), '\\');
        }
        if (in_array($type, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $name = ltrim($text, '\\');
            $head = strtolower(explode('\\', $name)[0]);

            return $type === T_NAME_QUALIFIED && isset($imports[$head])
                ? $imports[$head].substr($name, strlen($head))
                : $name;
        }
        if ($type === T_STRING) {
            return $imports[strtolower($text)] ?? null;
        }

        return null;
    }

    private function next(array $tokens, int $i): ?int
    {
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            if (! is_array($tokens[$j]) || ! in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $j;
            }
        }

        return null;
    }

    private function previous(array $tokens, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (! is_array($tokens[$j]) || ! in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $j;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function files(ExtensionManifest $manifest, array $dirs, string $suffix): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (is_dir($manifest->path($dir))) {
                foreach (File::allFiles($manifest->path($dir)) as $file) {
                    if (str_ends_with($file->getFilename(), $suffix)) {
                        $files[] = str_replace('\\', '/', $file->getPathname());
                    }
                }
            }
        }
        sort($files);

        return array_values(array_unique($files));
    }

    private function relative(ExtensionManifest $manifest, string $file): string
    {
        return 'extensions/'.$manifest->id.'/'.ltrim(substr(str_replace('\\', '/', $file), strlen($manifest->path())), '/');
    }
}
