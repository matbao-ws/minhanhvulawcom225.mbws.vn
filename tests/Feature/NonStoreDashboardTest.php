<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\FeatureSetting;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A website that sells nothing (catalog off) shows its content on the dashboard, never sales figures. */
class NonStoreDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'catalog'], ['is_enabled' => false]);
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        $this->admin = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['*']])->id]);
    }

    public function test_a_website_that_sells_nothing_shows_its_content_instead_of_sales(): void
    {
        Post::query()->create(['title' => ['vi' => 'Bài một'], 'slug' => 'bai-mot', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);
        Post::query()->create(['title' => ['vi' => 'Bài hai'], 'slug' => 'bai-hai', 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);
        ContactSubmission::query()->create(['name' => 'Khách', 'phone' => '0900000000', 'message' => 'Xin báo giá', 'is_read' => false, 'is_spam' => false]);

        $this->actingAs($this->admin)->get('/vi/admin')->assertOk()
            ->assertViewHas('isStore', false)
            // Content, with links to its screens.
            ->assertSee(__('admin.dashboard_page.content_stats.posts'))
            ->assertSee(__('admin.dashboard_page.content_stats.contacts'))
            ->assertSee(route('admin.contact-submissions.index'), false)
            ->assertSee(__('admin.dashboard_page.welcome_features_content.posts'))
            // No sales: no revenue chart, no order cards, no order shortcuts.
            ->assertDontSee('id="revenueChart"', false)
            ->assertDontSee(__('admin.dashboard_page.welcome_features.orders'))
            ->assertViewMissing('metrics');
    }

    public function test_a_selling_website_keeps_its_sales_dashboard(): void
    {
        FeatureSetting::query()->where('feature_code', 'catalog')->update(['is_enabled' => true]);

        $this->actingAs($this->admin)->get('/vi/admin')->assertOk()
            ->assertViewHas('isStore', true)
            ->assertSee('id="revenueChart"', false)
            ->assertSee(__('admin.dashboard_page.welcome_features.orders'));
    }
}
