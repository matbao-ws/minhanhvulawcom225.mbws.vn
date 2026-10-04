<?php

namespace App\Providers;

use App\Extension\Registries\AdminMenu;
use App\Support\Extensions\ExtensionAutoloader;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionManifest;
use App\Support\Extensions\ExtensionState;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Loads the website's extensions (docs/EXTENSIONS_DESIGN.md, section 2). This class owns the whole
 * lifecycle, the same for every extension: autoload, config, the extension provider's register();
 * then views, translations and migrations; the provider's boot() (its menu labels can already be
 * translated); then routes under fixed prefixes. A failure at any step marks that one extension
 * "failed", drops what it registered in the admin menu, and the website carries on without it.
 *
 * The extension:* commands never load extensions, so they keep working to rescue a website.
 */
class ExtensionServiceProvider extends ServiceProvider
{
    /** @var array<string, array{manifest: ExtensionManifest, provider: ServiceProvider}> */
    private array $extensions = [];

    public function register(): void
    {
        $this->app->singletonIf(AdminMenu::class);

        if (! $this->app->bound(ExtensionManager::class)) {
            $this->app->singleton(ExtensionManager::class, fn ($app) => new ExtensionManager(
                $app->basePath(),
                new ExtensionState($app->basePath()),
            ));
        }

        if ($this->rescueCommand()) {
            return;
        }

        $manager = $this->app->make(ExtensionManager::class);
        foreach ($manager->ready() as $manifest) {
            try {
                ExtensionAutoloader::add($manifest);
                if (is_file($manifest->path('config.php'))) {
                    $this->mergeConfigFrom($manifest->path('config.php'), 'extensions.'.$manifest->id);
                }
                $class = $manifest->provider;
                if (! class_exists($class) || ! is_subclass_of($class, ServiceProvider::class)) {
                    throw new \RuntimeException("provider {$class} not found in src/ or not a service provider");
                }
                $provider = new $class($this->app);
                $provider->register();
                $this->extensions[$manifest->id] = ['manifest' => $manifest, 'provider' => $provider];
            } catch (Throwable $e) {
                $this->fail($manager, $manifest, $e);
            }
        }
    }

    public function boot(): void
    {
        $manager = $this->app->make(ExtensionManager::class);

        foreach ($this->extensions as $id => ['manifest' => $manifest, 'provider' => $provider]) {
            try {
                $this->loadResources($manifest);
                if (method_exists($provider, 'boot')) {
                    $this->app->call([$provider, 'boot']);
                }
                $this->loadRoutes($manifest);
                $manager->markLoaded($id);
            } catch (Throwable $e) {
                $this->fail($manager, $manifest, $e);
            }
        }
        $this->extensions = [];
    }

    private function loadResources(ExtensionManifest $manifest): void
    {
        $id = $manifest->id;

        if (is_dir($manifest->path('views'))) {
            $this->loadViewsFrom($manifest->path('views'), $id);
        }
        if (is_dir($manifest->path('lang'))) {
            $this->loadTranslationsFrom($manifest->path('lang'), $id);
        }
        if (is_dir($manifest->path('database/migrations'))) {
            $this->loadMigrationsFrom($manifest->path('database/migrations'));
        }
    }

    private function loadRoutes(ExtensionManifest $manifest): void
    {
        $id = $manifest->id;
        if ($this->app->routesAreCached()) {
            return;
        }
        $groups = [
            // Inside the admin: same locale prefix and guards as routes/admin.php.
            'admin.php' => fn () => Route::middleware(['web', 'setLocale', 'auth', 'admin', 'package.sync'])
                ->prefix('{locale}/admin/ext/'.$id)->where(['locale' => '[a-z]{2,3}'])->name("ext.{$id}."),
            // Public endpoints (webhooks, an app's API): the extension adds its own auth.
            'api.php' => fn () => Route::middleware('api')->prefix('api/ext/'.$id)->name("ext.{$id}.api."),
            'web.php' => fn () => Route::middleware('web')->prefix('ext/'.$id)->name("ext.{$id}.web."),
        ];
        $added = false;
        foreach ($groups as $file => $group) {
            if (is_file($manifest->path('routes/'.$file))) {
                $group()->group($manifest->path('routes/'.$file));
                $added = true;
            }
        }
        if ($added) {
            $routes = $this->app['router']->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        }
    }

    private function fail(ExtensionManager $manager, ExtensionManifest $manifest, Throwable $e): void
    {
        $manager->markFailed($manifest->id, $e);
        $this->app->make(AdminMenu::class)->forget($manifest->id);
        unset($this->extensions[$manifest->id]);
        Log::error("Extension {$manifest->id} failed to load: ".$e->getMessage(), ['exception' => $e]);
    }

    /** `php artisan extension:*` runs without extensions: it is how a broken one gets switched off. */
    private function rescueCommand(): bool
    {
        return $this->app->runningInConsole()
            && str_starts_with((string) ($_SERVER['argv'][1] ?? ''), 'extension:');
    }
}
