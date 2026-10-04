<?php

namespace Tests\Support;

use App\Providers\ExtensionServiceProvider;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionState;
use Illuminate\Support\Facades\File;

/**
 * A throwaway website folder with extensions in it, and a way to run the extension lifecycle
 * against it. Every extension gets a fresh id: a PHP class, once loaded, stays for the whole run.
 */
trait BuildsExtensions
{
    protected string $extensionSite;

    protected function makeExtensionSite(string $version = '1.2.0'): void
    {
        $this->extensionSite = sys_get_temp_dir().'/ext-site-'.uniqid();
        File::ensureDirectoryExists($this->extensionSite);
        file_put_contents($this->extensionSite.'/VERSION', $version."\n");
    }

    protected function removeExtensionSite(): void
    {
        File::deleteDirectory($this->extensionSite);
    }

    protected function uniqueExtensionId(string $prefix = 'x'): string
    {
        return $prefix.substr(bin2hex(random_bytes(5)), 0, 9);
    }

    /**
     * @param  array<string, mixed>  $manifest  overrides for extension.json
     * @param  array<string, string>  $files  path inside the extension => contents
     * @param  string  $register  body of the provider's register()
     * @param  string  $boot  body of the provider's boot()
     */
    protected function makeExtension(string $id, array $manifest = [], array $files = [], string $register = '', string $boot = ''): string
    {
        $studly = ucfirst($id);
        $dir = $this->extensionSite.'/extensions/'.$id;
        $files = [
            'extension.json' => json_encode(array_merge([
                'schema' => 1,
                'id' => $id,
                'name' => 'Extension '.$id,
                'version' => '1.0.0',
                'requires_core' => '>=1.2.0 <2.0.0',
                'provider' => "Extensions\\{$studly}\\{$studly}ServiceProvider",
            ], $manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "src/{$studly}ServiceProvider.php" => <<<PHP
                <?php

                namespace Extensions\\{$studly};

                use Illuminate\\Support\\ServiceProvider;

                class {$studly}ServiceProvider extends ServiceProvider
                {
                    public function register(): void
                    {
                        {$register}
                    }

                    public function boot(): void
                    {
                        {$boot}
                    }
                }
                PHP,
        ] + $files;

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($dir.'/'.$path));
            file_put_contents($dir.'/'.$path, $contents);
        }

        return $dir;
    }

    /** Run discover → ... → boot against the throwaway site, as the app does at start. */
    protected function loadExtensions(): ExtensionManager
    {
        $manager = new ExtensionManager($this->extensionSite, new ExtensionState($this->extensionSite));
        $this->app->instance(ExtensionManager::class, $manager);

        $provider = new ExtensionServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        return $manager;
    }
}
