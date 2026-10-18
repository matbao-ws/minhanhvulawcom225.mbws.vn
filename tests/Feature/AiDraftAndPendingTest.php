<?php

namespace Tests\Feature;

use App\Models\AiSuggestion;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** AI buttons on create forms (save a draft, then open the tool) and AI suggestions offered again. */
class AiDraftAndPendingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        $this->admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['*']])->id]);
    }

    public function test_create_forms_offer_the_ai_tools_as_save_a_draft_first(): void
    {
        $this->actingAs($this->admin)->get('/vi/admin/posts/create')->assertOk()
            ->assertSee('js-ai-draft-first', false)
            ->assertSee('data-ai-tool="faq"', false)
            ->assertSee('data-ai-tool="meta-suggest"', false)
            ->assertSee('data-ai-tool="seo-fix"', false)
            ->assertDontSee('js-ai-faq"', false)
            ->assertSee(__('ai.draft_first.hint'));
        $this->actingAs($this->admin)->get('/vi/admin/brands/create')->assertOk()->assertSee('data-ai-tool="collection"', false);
    }

    public function test_saving_with_an_ai_tool_lands_on_the_edit_page_with_that_tool(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/posts', [
            'title' => ['vi' => 'Thi công nhà phố'], 'content' => ['vi' => '<p>Nội dung</p>'], 'is_active' => '0',
            '_ai_next' => 'faq', '_ai_locale' => 'vi',
        ])->assertRedirect(route('admin.posts.edit', [Post::query()->sole(), 'ai' => 'faq', 'ai_locale' => 'vi']));
        $this->assertFalse(Post::query()->sole()->is_active);

        $cases = [
            ['/vi/admin/categories', ['name' => ['vi' => 'Ống nhựa'], 'is_active' => '0'], Category::class, 'admin.categories.edit'],
            ['/vi/admin/brands', ['name' => ['vi' => 'Bình Minh'], 'is_active' => '0'], Brand::class, 'admin.brands.edit'],
            ['/vi/admin/post-categories', ['name' => ['vi' => 'Tin tức'], 'is_active' => '0'], PostCategory::class, 'admin.post-categories.edit'],
            ['/vi/admin/pages', ['title' => ['vi' => 'Đổi trả'], 'is_active' => '0'], Page::class, 'admin.pages.edit'],
            ['/vi/admin/products', ['name' => ['vi' => 'Áo'], 'price' => 100000, 'stock_quantity' => 5, 'is_active' => '0'], Product::class, 'admin.products.edit'],
        ];
        foreach ($cases as [$url, $data, $model, $edit]) {
            $response = $this->actingAs($this->admin)->post($url, $data + ['_ai_next' => 'seo-fix']);
            $record = $model::query()->latest('id')->firstOrFail();
            $response->assertRedirect(route($edit, [$record, 'ai' => 'seo-fix']));
            $this->assertFalse((bool) $record->is_active, $model);
        }
    }

    public function test_an_unknown_tool_is_an_ordinary_save(): void
    {
        $this->actingAs($this->admin)->post('/vi/admin/brands', ['name' => ['vi' => 'Tiền Phong'], '_ai_next' => 'https://evil.example'])
            ->assertRedirect(route('admin.brands.index'));
    }

    public function test_the_edit_page_offers_unreviewed_suggestions_still_valid_for_the_body(): void
    {
        $post = Post::query()->create(['title' => ['vi' => 'Thi công'], 'slug' => 'thi-cong', 'content' => ['vi' => '<p>Hiện tại</p>'], 'is_active' => true]);
        $other = User::factory()->create(['role_id' => $this->admin->role_id]);
        $make = fn (array $attributes) => AiSuggestion::query()->create($attributes + [
            'gateway_suggestion_id' => 1, 'entity_type' => 'post', 'entity_id' => $post->id, 'locale' => 'vi',
            'status' => AiSuggestion::PROPOSED, 'user_id' => $this->admin->id,
        ]);
        $make(['task' => 'content.faq', 'old_values' => ['html' => '<p>Hiện tại</p>'], 'options' => [['question' => 'Câu hỏi đang chờ?', 'answer' => 'Có.']]]);
        // Written for a body that has changed since: applying it would only conflict.
        $make(['task' => 'content.edit', 'old_values' => ['html' => '<p>Cũ</p>'], 'options' => [['html' => '<p>Bản cũ</p>', 'changes' => []]]]);
        // Someone else's, already applied, or too old.
        $make(['task' => 'meta.suggest', 'user_id' => $other->id, 'old_values' => [], 'options' => [['title' => 'Của người khác', 'description' => 'x']]]);
        $make(['task' => 'content.internal_links', 'status' => AiSuggestion::APPLIED, 'old_values' => ['html' => '<p>Hiện tại</p>'], 'options' => []]);
        $old = $make(['task' => 'seo.fix_suggest', 'old_values' => [], 'options' => [['seo_title' => 'Quá hạn']]]);
        $old->forceFill(['created_at' => now()->subDays(AiSuggestion::TTL_DAYS + 1)])->save();

        $this->actingAs($this->admin)->get("/vi/admin/posts/{$post->id}/edit")->assertOk()
            ->assertSee('js-ai-pending', false)
            ->assertSee(__('ai.pending.title', ['count' => 1]))
            ->assertSee(__('ai.pending.tasks.content_faq'))
            ->assertSee('"task":"content.faq"', false)
            ->assertSee(str_replace('/', '\/', route('admin.ai.faq.apply', ['suggestion' => AiSuggestion::query()->where('task', 'content.faq')->value('id')])), false)
            ->assertDontSee('"task":"content.edit"', false)
            ->assertDontSee('"task":"meta.suggest"', false)
            ->assertDontSee('"task":"content.internal_links"', false)
            ->assertDontSee('"task":"seo.fix_suggest"', false);
    }

    public function test_no_card_without_unreviewed_suggestions(): void
    {
        $post = Post::query()->create(['title' => ['vi' => 'Thi công'], 'slug' => 'thi-cong', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);

        $this->actingAs($this->admin)->get("/vi/admin/posts/{$post->id}/edit")->assertOk()->assertDontSee('js-ai-pending', false);
    }
}
