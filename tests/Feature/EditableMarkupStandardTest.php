<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\User;
use App\Services\SiteContentService;
use App\Services\SiteListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The markup standard, asserted against the code that has to honour it.
 *
 * A convention nobody checks drifts from the implementation within a release, and
 * then the document is worse than nothing: a theme cut follows it and the editor
 * does not recognise the result. Each test here pins one rule the standard states.
 *
 * @see .agents/skills/connect-ecommerce-frontend/references/editable-markup-standard.md
 */
class EditableMarkupStandardTest extends TestCase
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

    private function standard(): string
    {
        return file_get_contents(
            base_path('.agents/skills/connect-ecommerce-frontend/references/editable-markup-standard.md')
        );
    }

    public function test_the_standard_is_reachable_from_the_skill_reading_order(): void
    {
        // A reference nothing routes to is a reference nobody reads.
        $this->assertFileExists(base_path('.agents/skills/connect-ecommerce-frontend/references/editable-markup-standard.md'));
        $this->assertStringContainsString(
            'editable-markup-standard.md',
            file_get_contents(base_path('.agents/skills/connect-ecommerce-frontend/SKILL.md')),
        );
        $this->assertStringContainsString(
            'editable-markup-standard.md',
            file_get_contents(base_path('AI_BUILD_PROMPT.md')),
        );
    }

    public function test_key_shape_the_standard_documents_is_the_shape_the_server_accepts(): void
    {
        $lists = app(SiteListService::class);

        // Documented as: lowercase, dot separated, segments of a-z 0-9 and hyphen.
        $this->assertTrue($lists->isValidId('leadership'));
        $this->assertTrue($lists->isValidId('product-grid'));
        // A dot inside a segment would address another region's storage.
        $this->assertFalse($lists->isValidId('home.hero'));
        $this->assertFalse($lists->isValidId('Hero'));
        $this->assertFalse($lists->isValidId('hero title'));
    }

    public function test_the_server_rejects_a_key_that_breaks_the_naming_rule(): void
    {
        foreach (['Home.Hero.Title', 'home hero title', 'home/hero'] as $key) {
            $this->actingAs($this->editor())
                ->patchJson('/vi/admin/site-blocks', [
                    'key' => $key,
                    'type' => SiteBlock::TYPE_TEXT,
                    'content_locale' => 'vi',
                    'value' => 'x',
                ])
                ->assertStatus(422, "khoá sai chuẩn được chấp nhận: {$key}");
        }
    }

    public function test_the_blade_slot_is_the_default_and_nothing_is_seeded(): void
    {
        // Rule 3: a fresh install renders the approved design from Blade alone.
        $this->actingAs($this->editor())->get('/vi/sandbox/inline-editor')->assertOk();

        $this->assertDatabaseCount('site_blocks', 0);
        $this->assertDatabaseCount('site_lists', 0);
    }

    public function test_an_emptied_region_is_distinct_from_one_never_edited(): void
    {
        // Rule 3: the empty string is a real value meaning "hidden on purpose".
        $site = app(SiteContentService::class);
        $this->assertFalse($site->isCleared('home.hero.title'));

        $site->updateLocale('home.hero.title', SiteBlock::TYPE_TEXT, 'vi', '', $this->editor()->id);
        app()->forgetInstance(SiteContentService::class);

        $this->assertTrue(app(SiteContentService::class)->isCleared('home.hero.title'));
        $this->assertNull(app(SiteContentService::class)->value('home.hero.title'));
    }

    public function test_the_authored_tag_is_kept_so_theme_css_still_applies(): void
    {
        // Rule 4: classes pass through and the authored tag is recorded, so a
        // cleared heading override falls back to what the theme wrote.
        $html = $this->actingAs($this->editor())->get('/vi/sandbox/inline-editor')->getContent();

        $this->assertMatchesRegularExpression(
            '/<p[^>]*data-block-key="dev\.sandbox\.intro"[^>]*data-block-base-tag="p"/',
            $html,
        );
    }

    public function test_a_repeatable_wrapper_renders_for_visitors_too(): void
    {
        /*
         * Rule 5. Emitting the wrapper only for an editor gives the same page a
         * different DOM after login, and a flex or grid parent changes shape the
         * moment an admin signs in — a layout bug only staff ever see.
         */
        $component = file_get_contents(resource_path('views/client/components/list-item.blade.php'));

        $this->assertStringContainsString('$attributes->class($canEdit ? [', $component);
        // The wrapper element itself is outside any @if on the permission.
        $this->assertStringNotContainsString('@if($canEdit)<div', $component);
    }

    public function test_each_nesting_level_is_its_own_list(): void
    {
        // Rule 6: a list only accepts a permutation of the ids it owns, which is
        // what stops a child crossing into a sibling parent.
        $editor = $this->editor();

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'dev.sections',
                'order' => ['intro', 'features', 'contact', 'speed'],
                'defaults' => ['intro', 'features', 'contact'],
            ])
            ->assertOk();

        app()->forgetInstance(SiteListService::class);
        $this->assertNotContains(
            'speed',
            app(SiteListService::class)->items('dev.sections', ['intro', 'features', 'contact']),
        );
    }

    public function test_the_editor_chrome_sits_directly_under_body(): void
    {
        /*
         * Rule 7. A transform, filter or perspective on an ancestor creates a
         * containing block, and `position: fixed` inside one anchors to that
         * element instead of the viewport. Keeping the includes as direct children
         * of <body> is what keeps a theme from being able to do that to them.
         */
        $layout = file_get_contents(resource_path('views/client/layouts/app.blade.php'));

        $tail = substr($layout, strpos($layout, '@yield(\'content\')'));
        foreach (['admin-bar', 'inline-blocks', 'inline-outline'] as $partial) {
            $this->assertStringContainsString("@include('client.partials.{$partial}')", $tail);
        }
        // Nothing may wrap them.
        $this->assertMatchesRegularExpression(
            '/@include\(\'client\.partials\.inline-outline\'\)\s*<\/body>/',
            $layout,
        );
    }

    public function test_database_driven_values_carry_no_editing_hook(): void
    {
        /*
         * Rule 8, the one that matters most: two places to edit one field is a
         * defect. Whichever screen saves last wins and neither admin knows the
         * other exists.
         */
        foreach (['catalog/category', 'blog/category'] as $view) {
            $blade = file_get_contents(resource_path("views/client/{$view}.blade.php"));

            $this->assertStringNotContainsString('data-block-key', $blade, "{$view} tự viết hook");
            // Product and post fields are printed plainly, never wrapped.
            $this->assertStringNotContainsString('<x-client::editable key="'.explode('/', $view)[0].'.item', $blade);
        }
    }
}
