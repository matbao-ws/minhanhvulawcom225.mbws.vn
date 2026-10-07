<?php

namespace App\Support\Extensions;

/**
 * Extensions\<Id>\Foo\Bar → extensions/<id>/src/Foo/Bar.php, for loaded extensions only — so the
 * website's composer.json (a core file) never changes. A class name outside an extension's
 * namespace, or with anything but letters, digits, _ and \, is never turned into a path.
 */
class ExtensionAutoloader
{
    /** @var array<string, string> namespace prefix => src directory */
    private static array $prefixes = [];

    private static bool $registered = false;

    public static function add(ExtensionManifest $manifest): void
    {
        self::$prefixes[$manifest->namespace()] = $manifest->path('src');

        if (! self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            if ($relative === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $relative)) {
                return;
            }
            $file = $dir.'/'.str_replace('\\', '/', $relative).'.php';
            if (is_file($file)) {
                require_once $file;
            }

            return;
        }
    }

    /** Tests only: forget every prefix. */
    public static function reset(): void
    {
        self::$prefixes = [];
    }
}
