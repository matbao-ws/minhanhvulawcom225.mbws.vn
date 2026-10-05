<?php

namespace App\Console\Commands;

use App\Support\CoreManifest;
use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionManifest;
use App\Support\PermissionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The standard skeleton of an extension (docs/EXTENSIONS.md): manifest, provider, one admin page
 * with its menu entry and permission, translations, an empty migrations folder. Everyone starts from
 * the same layout instead of inventing one.
 */
class ExtensionMake extends Command
{
    protected $signature = 'extension:make {id : Lowercase letters and digits, e.g. dealers} {--name= : Display name}';

    protected $description = 'Create the skeleton of a new extension in extensions/<id>';

    public function handle(ExtensionManager $extensions, CoreManifest $core): int
    {
        $id = (string) $this->argument('id');
        $problem = match (true) {
            ! preg_match(ExtensionManifest::ID_PATTERN, $id) => 'the id is lowercase letters and digits only (2–31 characters), e.g. dealers',
            in_array($id, ExtensionManifest::RESERVED_IDS, true) => "\"{$id}\" is reserved",
            in_array($id, PermissionRegistry::coreModules(), true) => "\"{$id}\" is a core permission module",
            is_dir($extensions->root().'/'.$id) => "extensions/{$id} already exists",
            default => null,
        };
        if ($problem !== null) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        $studly = ucfirst($id);
        $name = (string) ($this->option('name') ?: $studly);
        [$major, $minor] = array_map('intval', array_pad(explode('.', $core->version()), 2, 0));
        $dir = $extensions->root().'/'.$id;

        foreach ($this->files($id, $studly, $name, ">={$major}.{$minor}.0 <".($major + 1).'.0.0') as $path => $contents) {
            File::ensureDirectoryExists(dirname($dir.'/'.$path));
            file_put_contents($dir.'/'.$path, $contents);
        }

        $this->components->info("Created extensions/{$id}.");
        $this->line("  Admin page: /vi/admin/ext/{$id} (menu \"{$name}\", permission {$id}.view)");
        $this->line("  Check it:   php artisan extension:check {$id}");

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function files(string $id, string $studly, string $name, string $requires): array
    {
        $json = json_encode([
            'schema' => 1,
            'id' => $id,
            'name' => $name,
            'version' => '0.1.0',
            'requires_core' => $requires,
            'provider' => "Extensions\\{$studly}\\{$studly}ServiceProvider",
            'permissions' => [['code' => "{$id}.view", 'label' => "Xem {$name}"]],
            'description' => '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

        return [
            'extension.json' => $json,
            "src/{$studly}ServiceProvider.php" => <<<PHP
                <?php

                namespace Extensions\\{$studly};

                use App\\Extension\\Registries\\AdminMenu;
                use Illuminate\\Support\\ServiceProvider;

                /**
                 * Register this extension's logic here: listeners, menu entries, its own bindings.
                 * The core loads views, translations, migrations and routes itself — do not call
                 * loadRoutesFrom / loadViewsFrom / loadMigrationsFrom.
                 */
                class {$studly}ServiceProvider extends ServiceProvider
                {
                    public function register(): void
                    {
                    }

                    public function boot(): void
                    {
                        AdminMenu::register('{$id}', [
                            'key' => '{$id}.index',
                            'label' => __('{$id}::messages.menu'),
                            'icon' => 'solar:widget-add-line-duotone',
                            'route' => 'ext.{$id}.index',
                            'permission' => '{$id}.view',
                        ]);
                    }
                }

                PHP,
            "src/Http/Controllers/{$studly}Controller.php" => <<<PHP
                <?php

                namespace Extensions\\{$studly}\\Http\\Controllers;

                use Illuminate\\Routing\\Controller;

                class {$studly}Controller extends Controller
                {
                    public function index()
                    {
                        return view('{$id}::index');
                    }
                }

                PHP,
            'routes/admin.php' => <<<PHP
                <?php

                use Extensions\\{$studly}\\Http\\Controllers\\{$studly}Controller;
                use Illuminate\\Support\\Facades\\Route;

                // Served at /{locale}/admin/ext/{$id}/..., behind the admin login. Names become ext.{$id}.*
                Route::get('/', [{$studly}Controller::class, 'index'])->middleware('can:{$id}.view')->name('index');

                PHP,
            'views/index.blade.php' => <<<BLADE
                @extends('admin.layouts.app')

                @section('title', __('{$id}::messages.title'))

                @section('content')
                    <x-admin.page-header :title="__('{$id}::messages.title')" />

                    <div class="card">
                        <div class="card-body">
                            {{ __('{$id}::messages.intro') }}
                        </div>
                    </div>
                @endsection

                BLADE,
            'lang/vi/messages.php' => "<?php\n\nreturn [\n    'menu' => '{$name}',\n    'title' => '{$name}',\n    'intro' => 'Trang của extension {$name}.',\n];\n",
            'lang/en/messages.php' => "<?php\n\nreturn [\n    'menu' => '{$name}',\n    'title' => '{$name}',\n    'intro' => 'The {$name} extension page.',\n];\n",
            'database/migrations/.gitkeep' => '',
            'README.md' => "# {$name}\n\nExtension `{$id}` — xem docs/EXTENSIONS.md của core.\n\n- Bảng của extension phải bắt đầu bằng `ext_{$id}_`.\n- Quyền khai báo trong `extension.json`, dạng `{$id}.<action>`.\n- Kiểm tra: `php artisan extension:check {$id}`.\n",
        ];
    }
}
