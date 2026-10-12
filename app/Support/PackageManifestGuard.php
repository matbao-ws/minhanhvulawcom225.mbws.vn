<?php

namespace App\Support;

use Composer\Autoload\ClassLoader;

/**
 * Drops Laravel's cached package manifest when it names a service provider vendor/ no longer has.
 *
 * `bootstrap/cache/packages.php` lists the providers of every installed package, and Laravel reads
 * it before anything else — including before `package:discover`, the command meant to rebuild it.
 * When vendor/ is replaced (a core update with a new composer.lock, or a deploy without the dev
 * packages a previous deploy shipped), a manifest naming such a package stops every artisan command
 * and every request with "Class …ServiceProvider not found". That is how the 1.4.0-beta.4 update
 * failed on websites deployed with laravel/pail in vendor/.
 *
 * Called from bootstrap/app.php, so it already runs on the new code during an update, whichever
 * version of the updater moved the files. Deleting the manifest is always safe: Laravel rebuilds it
 * from vendor/composer/installed.json on the next boot.
 */
final class PackageManifestGuard
{
    /**
     * @return bool whether a stale manifest was removed
     */
    public static function clearIfStale(string $cacheDirectory): bool
    {
        $manifest = $cacheDirectory.'/packages.php';
        if (! is_file($manifest)) {
            return false;
        }

        $packages = @include $manifest;
        if (is_array($packages) && ! self::namesMissingProvider($packages)) {
            return false;
        }

        // services.php is compiled from the same provider list, so it names the same missing classes.
        foreach ([$manifest, $cacheDirectory.'/services.php'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $packages
     */
    private static function namesMissingProvider(array $packages): bool
    {
        foreach ($packages as $package) {
            foreach ((array) (is_array($package) ? ($package['providers'] ?? []) : []) as $provider) {
                if (is_string($provider) && ! self::exists($provider)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Asks Composer where the class lives rather than loading it: this runs on every request. */
    private static function exists(string $class): bool
    {
        if (class_exists($class, false)) {
            return true;
        }

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($loader->findFile($class) !== false) {
                return true;
            }
        }

        return false;
    }
}
