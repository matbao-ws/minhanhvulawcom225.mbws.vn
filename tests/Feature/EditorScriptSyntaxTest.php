<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The inline editor is JavaScript written inside Blade. A syntax error anywhere in it stops the
 * whole editor on every website, and string assertions never notice one: a regex literal once
 * shipped broken across two lines. Every inline script of an editing page goes through
 * `node --check` (skipped where Node is not installed).
 */
class EditorScriptSyntaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_inline_script_of_the_editor_parses(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node is not installed.');
        }

        (new PermissionSeeder)->run();
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'], 'slug' => 'gioi-thieu', 'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true, 'published_at' => now()->subDay(),
        ]);
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $html = $this->get('/vi/chinh-sach/gioi-thieu')->assertOk()->getContent();
        preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*type="application/(?:ld\+)?json")[^>]*>(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'the editing page carries inline scripts');

        foreach ($matches[1] as $index => $script) {
            $file = tempnam(sys_get_temp_dir(), 'editor-js').'.js';
            file_put_contents($file, $script);
            $process = new Process([$node, '--check', $file]);
            $process->run();
            @unlink($file);

            $this->assertTrue($process->isSuccessful(), "inline script #{$index} does not parse:\n".$process->getErrorOutput());
        }
    }
}
