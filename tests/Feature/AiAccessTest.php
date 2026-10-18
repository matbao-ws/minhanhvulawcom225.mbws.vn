<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\Packages\PackageSync;
use App\Support\AiAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI buttons, menu items and pages are always shown; without the plan, the feature or the
 * permission they are locked and say why (App\Support\AiAccess, EnsureAiAccess).
 */
class AiAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ai_seo', 'ai_research', 'catalog', 'cms_page'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_project_token']);
        Http::fake(['gw.test/*' => Http::response(['ok' => true, 'items' => [], 'usage' => ['credits_used' => 0, 'credits_limit' => 10]])]);

        $this->editor = User::factory()->create(['role_id' => Role::query()->create([
            'name' => 'Editor', 'permissions' => ['posts.view', 'posts.create', 'posts.update', 'ai.use', 'ai.apply'],
        ])->id]);
    }

    public function test_ai_seo_comes_with_hero_4_and_research_with_hero_5(): void
    {
        $packages = app(PackageSync::class);

        $this->assertNotContains('ai_seo', $packages->featuresFor('HERO3'));
        $this->assertContains('ai_seo', $packages->featuresFor('HERO4'));
        $this->assertNotContains('ai_research', $packages->featuresFor('HERO4'));
        $this->assertContains('ai_research', $packages->featuresFor('HERO5'));

        $this->assertSame('Hero 4', app(AiAccess::class)->minimumPlan('ai_seo'));
        $this->assertSame('Hero 5', app(AiAccess::class)->minimumPlan('ai_research'));
    }

    public function test_it_names_the_reason_and_the_way_out(): void
    {
        $access = app(AiAccess::class);
        $this->assertTrue($access->check($this->editor)['allowed']);

        // Sold without it: the plan that has it, and the upgrade link.
        FeatureSetting::query()->where('feature_code', 'ai_research')->update(['is_enabled' => false, 'package_enabled' => false, 'is_overridden' => false]);
        $plan = $access->check($this->editor, 'ai_research');
        $this->assertSame([false, 'plan'], [$plan['allowed'], $plan['reason']]);
        $this->assertStringContainsString('Hero 5', $plan['message']);
        $this->assertSame(AiAccess::SUPPORT_URL, $plan['action_url']);

        // Research needs AI SEO first: switched off, that is the reason given.
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);
        $off = $access->check($this->editor, 'ai_research');
        $this->assertSame(['off', 'ai_seo'], [$off['reason'], $off['feature']]);

        // On, but not for this role: no upgrade link, ask the site's admin.
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => true]);
        $permission = $access->check($this->editor, 'ai_seo', 'products.update');
        $this->assertSame(['permission', null], [$permission['reason'], $permission['action_url']]);
    }

    public function test_a_superadmin_is_never_locked_but_sees_what_the_customer_sees(): void
    {
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false, 'package_enabled' => false, 'is_overridden' => false]);
        $root = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true])->id]);

        $access = app(AiAccess::class)->check($root);

        $this->assertSame([true, 'plan'], [$access['allowed'], $access['bypassed']]);
    }

    public function test_an_ai_page_opened_while_locked_says_why_and_a_call_is_refused(): void
    {
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false, 'package_enabled' => false, 'is_overridden' => false]);

        $this->actingAs($this->editor)->get('/vi/admin/ai/bulk')->assertForbidden()
            ->assertSee('data-ai-locked-page="plan"', false)
            ->assertSee('Hero 4')
            ->assertSee(AiAccess::SUPPORT_URL, false);

        $this->actingAs($this->editor)->postJson('/vi/admin/ai/content-edits', [])->assertForbidden()
            ->assertJsonPath('reason', 'ai_locked')->assertJsonPath('locked.reason', 'plan');

        // No AI call left the site (the layout's mail counter is not AI).
        Http::assertNotSent(fn ($request) => ! str_contains($request->url(), '/v1/mail/'));
    }

    public function test_locked_buttons_stay_on_the_editor_without_their_tools(): void
    {
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);
        $post = Post::query()->create(['title' => ['vi' => 'Bài'], 'slug' => 'bai', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);

        $this->actingAs($this->editor)->get("/vi/admin/posts/{$post->id}/edit")->assertOk()
            ->assertSee(__('ai.edit.button'))->assertSee(__('ai.meta.button'))->assertSee(__('ai.seo.button'))
            ->assertSee('is-ai-locked', false)->assertSee('data-ai-locked=', false)
            ->assertDontSee('js-ai-faq', false)->assertDontSee('js-ai-seo-fix', false);

        // Unlocked, the same buttons open their tools.
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => true]);
        $this->actingAs($this->editor)->get("/vi/admin/posts/{$post->id}/edit")->assertOk()
            ->assertSee('js-ai-faq', false)->assertSee('js-ai-seo-fix', false);
    }

    public function test_the_dashboard_keeps_the_ai_cards_locked(): void
    {
        FeatureSetting::query()->where('feature_code', 'ai_seo')->update(['is_enabled' => false]);

        $this->actingAs($this->editor)->get('/vi/admin')->assertOk()
            ->assertSee('data-ai-usage-locked', false)
            ->assertSee('data-seo-tasks-locked="off"', false);
    }
}
