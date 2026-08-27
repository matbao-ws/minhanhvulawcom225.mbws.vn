<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\SiteListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stress page, checked against the standard it exists to exercise.
 *
 * Its value is only as a conformance harness, so it has to conform itself: a demo
 * that breaks the key rules teaches the wrong thing to whoever copies it.
 *
 * @see .agents/skills/connect-ecommerce-frontend/references/editable-markup-standard.md
 */
class InlineEditorStressPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\PermissionSeeder())->run();
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
    }

    private function editor(): User
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function html(?User $user = null): string
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get('/vi/sandbox/inline-editor-stress')->assertOk()->getContent();
    }

    public function test_every_key_on_the_page_is_namespaced_to_it(): void
    {
        // The draft shared its section partials with the other demo, which meant
        // both pages wrote to the same rows. Keys are page-scoped for a reason.
        preg_match_all('/data-block-key="([^"]+)"/', $this->html($this->editor()), $matches);

        $this->assertNotEmpty($matches[1]);
        foreach (array_unique($matches[1]) as $key) {
            $this->assertStringStartsWith('stress.', $key, "khoá không thuộc namespace của trang: {$key}");
        }
    }

    public function test_every_key_matches_the_documented_shape(): void
    {
        preg_match_all('/data-block-key="([^"]+)"/', $this->html($this->editor()), $matches);

        foreach (array_unique($matches[1]) as $key) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)+$/',
                $key,
                "khoá sai chuẩn đặt tên: {$key}",
            );
        }
    }

    public function test_the_page_covers_the_layouts_that_break_this_kind_of_tool(): void
    {
        $html = $this->html($this->editor());

        foreach ([
            'stress-hero__overlay' => 'lớp phủ absolute',
            'stress-grid' => 'CSS grid auto-fill',
            'stress-flex' => 'hàng flex căn sát',
            'stress-transformed' => 'ancestor có transform',
            'stress-clipped' => 'cha cắt tràn',
            'stress-table' => 'bảng',
            'stress-tall' => 'khoảng cuộn dài',
        ] as $marker => $what) {
            $this->assertStringContainsString($marker, $html, "thiếu ca kiểm thử: {$what}");
        }
    }

    public function test_sections_nest_three_levels_each_with_its_own_list(): void
    {
        $html = $this->html($this->editor());

        foreach ([
            'stress.sections',
            'stress.sections.features.children',
            'stress.sections.speed.children',
        ] as $listKey) {
            $this->assertStringContainsString('data-section-list="'.$listKey.'"', $html, "thiếu cấp: {$listKey}");
        }
    }

    public function test_a_grandchild_cannot_be_moved_into_its_grandparent(): void
    {
        // Three levels is where a scoping mistake stops being theoretical.
        $this->actingAs($this->editor())
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'stress.sections',
                'order' => ['stress-intro', 'stress-features', 'stress-contact', 'stress-cache'],
                'defaults' => ['stress-intro', 'stress-features', 'stress-contact'],
            ])
            ->assertOk();

        app()->forgetInstance(SiteListService::class);
        $this->assertNotContains(
            'stress-cache',
            app(SiteListService::class)->items('stress.sections', ['stress-intro', 'stress-features', 'stress-contact']),
        );
    }

    public function test_the_page_seeds_nothing_and_hides_everything_from_a_visitor(): void
    {
        $this->html();

        $this->assertDatabaseCount('site_blocks', 0);
        $this->assertDatabaseCount('site_lists', 0);
        $this->assertStringNotContainsString('data-block-key', $this->html());
        $this->assertStringNotContainsString('client-block-toolbar', $this->html());
    }

    public function test_the_page_does_not_exist_outside_local(): void
    {
        // A stress harness must never be reachable on a customer's site.
        $routes = file_get_contents(base_path('routes/client.php'));

        $this->assertStringContainsString("app()->environment(['local', 'testing'])", $routes);
        $this->assertStringContainsString('inline-editor-stress', $routes);
    }
}
