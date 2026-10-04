<?php

namespace Tests\Feature;

use App\Support\Extensions\ExtensionManager;
use App\Support\Extensions\ExtensionManifest;
use App\Support\Extensions\ExtensionState;
use App\Support\Extensions\InvalidExtension;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Which extensions may run (docs/EXTENSIONS_DESIGN.md, sections 1, 2 and 4): extension.json rules,
 * the on/off switch in storage/app/core/extensions.json, and requires_core — all without running
 * any extension code.
 */
class ExtensionManagerTest extends TestCase
{
    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = sys_get_temp_dir().'/extensions-'.uniqid();
        File::ensureDirectoryExists($this->site);
        file_put_contents($this->site.'/VERSION', "1.2.0\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->site);

        parent::tearDown();
    }

    public function test_a_valid_manifest_derives_every_name_from_its_id(): void
    {
        $this->extension('dealers', ['permissions' => [['code' => 'dealers.view', 'label' => 'Xem đại lý']]]);

        $manifest = ExtensionManifest::fromDirectory($this->site.'/extensions/dealers');

        $this->assertSame('dealers', $manifest->id);
        $this->assertSame('Extensions\\Dealers\\', $manifest->namespace());
        $this->assertSame('ext_dealers_', $manifest->tablePrefix());
        $this->assertSame(['dealers.view'], $manifest->permissionCodes());
        $this->assertTrue($manifest->supportsCore('1.9.0'));
        $this->assertFalse($manifest->supportsCore('2.0.0'));
    }

    public function test_invalid_manifests_say_what_is_wrong(): void
    {
        $cases = [
            'id differs from the folder' => ['folder' => 'dealers', 'id' => 'agents', 'error' => 'must equal the folder name'],
            'id with a hyphen' => ['folder' => 'erp-sync', 'id' => 'erp-sync', 'error' => 'id must match'],
            'reserved id' => ['folder' => 'admin', 'id' => 'admin', 'error' => 'is reserved'],
            'provider outside the namespace' => ['folder' => 'misa', 'id' => 'misa', 'provider' => 'App\\Providers\\AppServiceProvider', 'error' => 'provider must be a class in Extensions\\Misa\\'],
            'bad constraint' => ['folder' => 'misa', 'id' => 'misa', 'requires_core' => 'soon', 'error' => 'requires_core is not a version constraint'],
            'foreign permission' => ['folder' => 'misa', 'id' => 'misa', 'permissions' => [['code' => 'orders.delete', 'label' => 'x']], 'error' => 'must be "misa.<action>"'],
            'wrong schema' => ['folder' => 'misa', 'id' => 'misa', 'schema' => 2, 'error' => 'schema must be 1'],
        ];

        foreach ($cases as $name => $case) {
            $folder = $case['folder'];
            unset($case['folder']);
            $error = $case['error'];
            unset($case['error']);
            File::deleteDirectory($this->site.'/extensions');
            $this->extension($folder, $case);

            try {
                ExtensionManifest::fromDirectory($this->site.'/extensions/'.$folder);
                $this->fail("{$name}: accepted");
            } catch (InvalidExtension $e) {
                $this->assertStringContainsString($error, $e->getMessage(), $name);
            }
        }

        File::ensureDirectoryExists($this->site.'/extensions/empty');
        $this->expectExceptionMessage('extension.json is missing');
        ExtensionManifest::fromDirectory($this->site.'/extensions/empty');
    }

    public function test_the_manager_sorts_extensions_into_ready_disabled_incompatible_and_invalid(): void
    {
        $this->extension('dealers');
        $this->extension('misa', ['requires_core' => '>=2.0.0']);
        $this->extension('loyalty');
        $this->extension('broken', ['id' => 'other']);
        (new ExtensionState($this->site))->disable('loyalty', 'thử');

        $all = $this->manager()->all();

        $this->assertSame(['broken', 'dealers', 'loyalty', 'misa'], array_keys($all));
        $this->assertSame(ExtensionManager::READY, $all['dealers']['status']);
        $this->assertSame([ExtensionManager::DISABLED, 'thử'], [$all['loyalty']['status'], $all['loyalty']['error']]);
        $this->assertSame(ExtensionManager::INCOMPATIBLE, $all['misa']['status']);
        $this->assertStringContainsString('requires core >=2.0.0, this core is 1.2.0', $all['misa']['error']);
        $this->assertSame(ExtensionManager::INVALID, $all['broken']['status']);
        $this->assertSame(['dealers'], array_map(fn ($m) => $m->id, $this->manager()->ready()));
    }

    public function test_the_on_off_switch_lives_in_a_file_and_survives_a_new_process(): void
    {
        $state = new ExtensionState($this->site);
        $state->disable('dealers', 'lỗi MISA');

        $this->assertFileExists($this->site.'/storage/app/core/extensions.json');
        $this->assertTrue((new ExtensionState($this->site))->isDisabled('dealers'));

        $state->enable('dealers');
        $this->assertFalse((new ExtensionState($this->site))->isDisabled('dealers'));
        $this->assertSame([], (new ExtensionState($this->site))->disabled());
    }

    public function test_extensions_enabled_false_loads_nothing(): void
    {
        $this->extension('dealers');
        config(['extensions.enabled' => false]);

        $this->assertSame([], $this->manager()->ready());
        $this->assertSame([], $this->manager()->incompatibleWith('9.0.0'));
    }

    public function test_the_update_check_reads_manifests_against_the_target_version(): void
    {
        $this->extension('dealers', ['requires_core' => '>=1.2.0 <2.0.0', 'version' => '1.4.0', 'name' => 'Quản lý đại lý']);
        $this->extension('misa', ['requires_core' => '^1.2 || ^2.0']);
        $this->extension('loyalty', ['requires_core' => '<2.0.0']);
        (new ExtensionState($this->site))->disable('loyalty');

        $manager = $this->manager();

        $this->assertSame([], $manager->incompatibleWith('1.9.4'));
        $this->assertSame(
            [['id' => 'dealers', 'name' => 'Quản lý đại lý', 'version' => '1.4.0', 'requires_core' => '>=1.2.0 <2.0.0']],
            $manager->incompatibleWith('2.0.0'),
        );
    }

    public function test_no_extensions_folder_means_nothing_changes(): void
    {
        $this->assertSame([], $this->manager()->all());
        $this->assertSame([], $this->manager()->ready());
        $this->assertSame([], $this->manager()->incompatibleWith('2.0.0'));
    }

    private function manager(): ExtensionManager
    {
        return new ExtensionManager($this->site, new ExtensionState($this->site));
    }

    /** @param  array<string, mixed>  $overrides */
    private function extension(string $folder, array $overrides = []): void
    {
        $id = $overrides['id'] ?? $folder;
        $manifest = array_merge([
            'schema' => 1,
            'id' => $id,
            'name' => ucfirst($id),
            'version' => '1.0.0',
            'requires_core' => '>=1.2.0 <2.0.0',
            'provider' => 'Extensions\\'.ucfirst((string) $id).'\\'.ucfirst((string) $id).'ServiceProvider',
        ], $overrides);

        File::ensureDirectoryExists($this->site.'/extensions/'.$folder);
        file_put_contents($this->site.'/extensions/'.$folder.'/extension.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));
    }
}
