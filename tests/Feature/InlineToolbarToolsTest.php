<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\SiteList;
use App\Models\User;
use App\Services\SiteContentService;
use App\Services\SiteListService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The inline toolbar's alignment, box actions (duplicate, move) and "Sửa với AI". */
class InlineToolbarToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
        foreach (['catalog', 'cms_page', 'menu'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        Category::query()->create(['name' => ['vi' => 'Điện thoại'], 'slug' => 'dien-thoai', 'is_active' => true]);
    }

    private function editor(array $permissions = ['pages.update']): User
    {
        $user = User::factory()->create(['role_id' => Role::query()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
        $this->actingAs($user);

        return $user;
    }

    public function test_alignment_is_a_token_rendered_as_a_class_for_every_visitor(): void
    {
        $this->editor();

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty', 'type' => SiteBlock::TYPE_TEXT, 'content_locale' => 'vi',
            'value' => 'Sắp có hàng', 'align' => 'center',
        ])->assertOk()->assertJsonPath('data.align', 'center');

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'catalog.category.empty', 'type' => SiteBlock::TYPE_TEXT, 'content_locale' => 'vi',
            'value' => 'Sắp có hàng', 'align' => 'diagonal',
        ])->assertStatus(422);

        auth()->logout();
        $this->get('/danh-muc/dien-thoai')->assertOk()->assertSee('client-align-center', false)
            ->assertSee('.client-align-center{text-align:center!important}', false);
    }

    public function test_a_save_without_alignment_keeps_it_and_null_returns_to_the_theme(): void
    {
        $user = $this->editor();
        $site = app(SiteContentService::class);
        $site->updateLocale('catalog.category.empty', SiteBlock::TYPE_TEXT, 'vi', 'A', $user->id, null, 'right');

        // The admin block screens never send an alignment: it stays.
        $site->updateLocale('catalog.category.empty', SiteBlock::TYPE_TEXT, 'vi', 'B', $user->id);
        $this->assertSame('right', SiteBlock::query()->value('align'));

        $site->updateLocale('catalog.category.empty', SiteBlock::TYPE_TEXT, 'vi', 'B', $user->id, null, null);
        $this->assertNull(SiteBlock::query()->value('align'));
    }

    public function test_a_box_is_duplicated_right_after_its_source_with_its_current_content(): void
    {
        $this->editor();

        $response = $this->postJson('/vi/admin/site-lists/items/duplicate', [
            'key' => 'about.team', 'item' => 'b', 'defaults' => ['a', 'b', 'c'], 'content_locale' => 'vi',
            'slots' => [
                ['slot' => 'name', 'type' => 'text', 'value' => 'Nguyễn Văn B'],
                ['slot' => 'bio', 'type' => 'html', 'value' => '<p>Kỹ sư <span style="color:#dc2626;font-size:40px">trưởng</span><script>x</script></p>', 'align' => 'center'],
                // No media permission: the copy keeps the template's picture.
                ['slot' => 'photo', 'type' => 'image', 'value' => '/storage/general/b.jpg'],
            ],
        ])->assertOk();

        $newId = $response->json('data.item');
        $this->assertSame(['a', 'b', $newId, 'c'], SiteList::query()->where('key', 'about.team')->value('items'));

        $lists = app(SiteListService::class);
        $name = SiteBlock::query()->where('key', $lists->itemKey('about.team', $newId, 'name'))->first();
        $bio = SiteBlock::query()->where('key', $lists->itemKey('about.team', $newId, 'bio'))->first();
        $this->assertSame('Nguyễn Văn B', $name->getTranslation('content', 'vi'));
        $this->assertSame('<p>Kỹ sư <span style="color:#dc2626;">trưởng</span></p>', $bio->getTranslation('content', 'vi'));
        $this->assertSame('center', $bio->align);
        $this->assertFalse(SiteBlock::query()->where('key', $lists->itemKey('about.team', $newId, 'photo'))->exists());
    }

    public function test_a_lone_region_is_copied_right_after_itself_with_the_same_tag(): void
    {
        $this->editor();
        $this->get('/danh-muc/dien-thoai')->assertOk()
            ->assertSee('data-copy-list="catalog.category.empty.extra"', false)
            ->assertSee('id="client-block-copy-actions"', false);

        $copy = fn (string $value) => $this->postJson('/vi/admin/site-lists/items/duplicate', [
            'key' => 'catalog.category.empty.extra', 'content_locale' => 'vi', 'defaults' => [],
            'slots' => [['slot' => 'text', 'type' => 'text', 'value' => $value, 'align' => 'right']],
        ])->assertOk()->json('data.item');

        $first = $copy('Bản sao một');
        // Copying the authored region again puts the newer copy first, right after it.
        $second = $copy('Bản sao hai');
        $this->assertSame([$second, $first], SiteList::query()->where('key', 'catalog.category.empty.extra')->value('items'));

        auth()->logout();
        $html = $this->get('/danh-muc/dien-thoai')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<p[^>]*client-align-right[^>]*>Bản sao hai<\/p>\s*<p[^>]*>Bản sao một<\/p>/u', $html);
        // A block copy needs no help to start a line; only inline copies get the class.
        $this->assertStringNotContainsString('client-copy-line"', $html);
        $this->assertStringNotContainsString('data-copy-list', $html);
    }

    public function test_a_list_item_renders_the_theme_element_it_replaces(): void
    {
        $this->editor();
        $render = fn (string $tag) => Blade::render(
            '<x-client::list-item list="page.benefits" item="a" :tag="$tag" class="flex">X</x-client::list-item>',
            ['tag' => $tag],
        );

        $this->assertMatchesRegularExpression('/^\s*<li\s[^>]*class="client-list-item flex"[^>]*data-list-item="a"/', $render('li'));
        $this->assertStringEndsWith('</li>', trim($render('li')));
        // Nothing that would make the remove button invalid HTML.
        $this->assertMatchesRegularExpression('/^\s*<div\s/', $render('a'));
    }

    public function test_duplicating_rejects_an_unknown_box_or_slot(): void
    {
        $this->editor();

        $this->postJson('/vi/admin/site-lists/items/duplicate', [
            'key' => 'about.team', 'item' => 'zz', 'defaults' => ['a'], 'content_locale' => 'vi', 'slots' => [],
        ])->assertStatus(422);

        $this->postJson('/vi/admin/site-lists/items/duplicate', [
            'key' => 'about.team', 'item' => 'a', 'defaults' => ['a'], 'content_locale' => 'vi',
            'slots' => [['slot' => '../other', 'type' => 'text', 'value' => 'x']],
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_lists', 0);
    }

    public function test_the_ai_rewrites_the_selected_text_as_plain_text(): void
    {
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_x']);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'ai_seo'], ['is_enabled' => true]);
        $sent = null;
        Http::fake([
            'gw.test/v1/project*' => Http::response(['ok' => true, 'project' => []]),
            'gw.test/v1/tasks/content.rewrite' => function (Request $request) use (&$sent) {
                $sent = $request->data();

                return Http::response(['ok' => true, 'suggestion_id' => 1, 'result' => ['text' => '  Ý tưởng của bạn, <b>thành</b> sự thật  '], 'usage' => ['credits_charged' => 1]]);
            },
        ]);
        $this->editor(['pages.update', 'ai.use']);

        $this->postJson('/vi/admin/ai/rewrite', ['text' => 'Mọi ý tưởng đều có thể thành hiện thực', 'mode' => 'shorten', 'kind' => 'heading'])
            ->assertOk()->assertJsonPath('data.text', 'Ý tưởng của bạn, thành sự thật');
        $this->assertSame('shorten', $sent['mode']);
        $this->assertSame('heading', $sent['kind']);
        $this->assertSame('vi', $sent['locale']);

        $this->postJson('/vi/admin/ai/rewrite', ['text' => 'x', 'mode' => 'magic'])->assertStatus(422);
    }

    public function test_the_ai_button_needs_ai_access_and_the_rewrite_needs_ai_use(): void
    {
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'ai_seo'], ['is_enabled' => true]);

        $this->editor(['pages.update', 'ai.use']);
        $this->get('/danh-muc/dien-thoai')->assertOk()
            ->assertSee('id="client-block-ai-control"', false)
            ->assertSee('id="client-block-align-control"', false)
            ->assertSee('data-client-block-action="duplicate"', false)
            ->assertSee('id="client-block-more-title"', false);

        $this->editor(['pages.update']);
        $this->get('/danh-muc/dien-thoai')->assertOk()
            ->assertDontSee('id="client-block-ai-control"', false)
            // Shown locked, explaining itself, like every AI button.
            ->assertSee('id="client-block-ai-locked"', false)
            ->assertSee('id="client-block-align-control"', false);
        $this->postJson('/vi/admin/ai/rewrite', ['text' => 'x', 'mode' => 'improve'])->assertForbidden();
    }

    /** "Sửa với AI" comes with AI SEO, which starts at Hero 4: below it the button names that plan and the route refuses. */
    public function test_below_hero_4_the_ai_button_is_locked_and_names_the_plan(): void
    {
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'ai_seo'], ['is_enabled' => false, 'package_enabled' => false, 'is_overridden' => false]);
        $this->editor(['pages.update', 'ai.use']);

        $html = $this->get('/danh-muc/dien-thoai')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="client-block-ai-control"', $html);
        $this->assertStringContainsString('id="client-block-ai-locked"', $html);
        $this->assertStringContainsString('Hero 4', html_entity_decode($html));

        $this->postJson('/vi/admin/ai/rewrite', ['text' => 'Xin chào', 'mode' => 'improve'])->assertStatus(403);
    }
}
