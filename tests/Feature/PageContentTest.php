<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'cms_page'],
            ['is_enabled' => true],
        );
        $role = Role::query()->create([
            'name' => 'Page editor',
            'permissions' => ['pages.view', 'pages.create', 'pages.update', 'pages.delete', 'media.view', 'media.create', 'media.delete'],
        ]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_page_routes_require_admin_permission(): void
    {
        $this->get('/vi/admin/pages')->assertRedirect('/vi/admin/login');

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)->get('/vi/admin/pages')->assertForbidden();
    }

    public function test_authorized_admin_can_render_page_metadata_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/vi/admin/pages/create')
            ->assertOk()
            ->assertSee('Tạo trang chính sách')
            ->assertSee('Tiêu đề')
            ->assertSee('SEO title')
            ->assertSee('aria-label="breadcrumb"', false)
            ->assertSee(route('admin.pages.index'), false)
            ->assertDontSee('grapesjs', false)
            ->assertDontSee('page-builder-canvas', false)
            ->assertDontSee('builder_data', false);
    }

    public function test_admin_can_create_and_update_a_page(): void
    {
        $this->actingAs($this->admin);

        $this->post('/vi/admin/pages', $this->payload())
            ->assertRedirect();

        $page = Page::query()->firstOrFail();
        $this->assertSame('Giới thiệu', $page->getTranslation('title', 'vi'));
        $this->assertTrue($page->is_active);
        $this->assertCount(0, $page->revisions);

        $updated = $this->payload();
        $updated['title']['vi'] = 'Giới thiệu mới';
        $this->put("/vi/admin/pages/{$page->id}", $updated)->assertRedirect();

        $this->assertSame('Giới thiệu mới', $page->fresh()->getTranslation('title', 'vi'));
        $this->assertCount(1, $page->fresh()->revisions);
    }

    public function test_public_api_only_returns_published_pages(): void
    {
        $published = Page::query()->create([
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'slug' => 'gioi-thieu',
            'published_html' => ['vi' => '<div>Xin chào</div>', 'en' => '<div>Hello</div>'],
            'is_active' => true,
            'published_at' => now(),
        ]);
        Page::query()->create([
            'title' => ['vi' => 'Bản nháp'],
            'slug' => 'ban-nhap',
            'is_active' => false,
        ]);

        $this->getJson('/api/public/pages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonMissingPath('data.0.css');

        $this->getJson('/api/public/pages/gioi-thieu?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.html', '<div>Xin chào</div>');
        $this->getJson('/api/public/pages/ban-nhap')->assertNotFound();
    }

    public function test_authorized_admin_can_preview_a_saved_page_in_requested_locale(): void
    {
        $page = $this->publishedPage();

        $this->actingAs($this->admin)
            ->get("/vi/admin/pages/{$page->id}/preview?content_locale=en")
            ->assertOk()
            ->assertSee('About us');
    }

    public function test_published_client_page_only_shows_edit_bar_to_authorized_admin(): void
    {
        $page = $this->publishedPage();
        $clientUrl = '/chinh-sach/'.$page->canonicalSlug('vi');

        $this->get($clientUrl)
            ->assertOk()
            ->assertSee('Trang giới thiệu')
            ->assertDontSee('client-admin-bar', false);

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)
            ->get($clientUrl)
            ->assertOk()
            ->assertDontSee('client-admin-bar', false);

        $roleWithoutPermission = Role::query()->create([
            'name' => 'Admin không sửa trang',
            'permissions' => [],
        ]);
        $adminWithoutPermission = User::factory()->create(['role_id' => $roleWithoutPermission->id]);
        $this->actingAs($adminWithoutPermission)
            ->get($clientUrl)
            ->assertOk()
            ->assertDontSee('client-admin-bar', false);

        $pageOnlyRole = Role::query()->create([
            'name' => 'Chỉ sửa trang',
            'permissions' => ['pages.view', 'pages.create', 'pages.update', 'pages.delete'],
        ]);
        $pageOnlyAdmin = User::factory()->create(['role_id' => $pageOnlyRole->id]);
        $this->actingAs($pageOnlyAdmin)
            ->get($clientUrl)
            ->assertOk()
            ->assertSee('client-inline-edit-button', false)
            ->assertDontSee('client-inline-media-picker', false);

        $this->actingAs($this->admin)
            ->get($clientUrl)
            ->assertOk()
            ->assertSee('client-admin-bar', false)
            ->assertSee('Sửa trực tiếp')
            ->assertSee('client-inline-edit-button', false)
            ->assertSee('client-inline-media-picker', false)
            ->assertSee('Thêm ảnh mới')
            ->assertSee('grid-auto-rows: 150px', false)
            ->assertSee('object-fit: contain', false)
            ->assertSee('mediaResourcesUrl', false)
            ->assertSee('const saveUrl =', false);
    }

    public function test_authorized_admin_can_save_a_page_from_client_inline_editor(): void
    {
        $page = $this->publishedPage();
        $oldEnglishHtml = $page->getTranslation('published_html', 'en', false);

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<section onclick="alert(1)"><h1>Nội dung mới</h1><script>alert(1)</script></section>',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.html', '<section><h1>Nội dung mới</h1></section>');

        $page->refresh();
        $this->assertSame('<section><h1>Nội dung mới</h1></section>', $page->getTranslation('published_html', 'vi', false));
        $this->assertSame($oldEnglishHtml, $page->getTranslation('published_html', 'en', false));
        $this->assertCount(1, $page->revisions);
    }

    public function test_inline_update_is_a_no_op_when_html_is_unchanged(): void
    {
        $page = $this->publishedPage();

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<section><h1>Nội dung mới</h1></section>',
            ])->assertOk();

        $page->refresh();
        $this->assertCount(1, $page->revisions);
        $storedHtml = $page->getTranslation('published_html', 'vi', false);
        $updatedAt = $page->updated_at;

        // Sending back the exact HTML already stored must not touch the row or create a revision.
        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => $storedHtml,
            ])->assertOk();

        $page->refresh();
        $this->assertCount(1, $page->revisions);
        $this->assertTrue($updatedAt->equalTo($page->updated_at));
    }

    public function test_inline_update_coalesces_revisions_within_ten_minutes(): void
    {
        $page = $this->publishedPage();

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<section><h1>Bản sửa 1</h1></section>',
            ])->assertOk();
        $page->refresh();
        $this->assertCount(1, $page->revisions);

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<section><h1>Bản sửa 2</h1></section>',
            ])->assertOk();
        $page->refresh();
        $this->assertCount(1, $page->revisions, 'A second distinct edit within the 10-minute window should coalesce, not add a new revision.');
        $this->assertSame('<section><h1>Bản sửa 2</h1></section>', $page->getTranslation('published_html', 'vi', false));

        $this->travel(11)->minutes();

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<section><h1>Bản sửa 3</h1></section>',
            ])->assertOk();
        $page->refresh();
        $this->assertCount(2, $page->revisions, 'Past the coalescing window, a fresh checkpoint revision should be created.');
    }

    public function test_customer_cannot_use_client_inline_update_endpoint(): void
    {
        $page = $this->publishedPage();
        $payload = [
            'content_locale' => 'vi',
            'published_html' => '<p>Không được phép</p>',
        ];

        $this->patchJson("/vi/admin/pages/{$page->id}/inline", $payload)
            ->assertUnauthorized();

        $customer = User::factory()->create(['role_id' => null]);

        $this->actingAs($customer)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", $payload)
            ->assertForbidden();
    }

    public function test_client_page_does_not_render_drafts_or_disabled_cms(): void
    {
        $draft = Page::query()->create([
            'title' => ['vi' => 'Bản nháp'],
            'slug' => 'ban-nhap-client',
            'published_html' => ['vi' => '<p>Chưa xuất bản</p>'],
            'is_active' => false,
        ]);

        $this->get('/chinh-sach/'.$draft->slug)->assertNotFound();

        $published = Page::query()->create([
            'title' => ['vi' => 'Trang đang hoạt động'],
            'slug' => 'trang-dang-hoat-dong',
            'published_html' => ['vi' => '<p>Đã xuất bản</p>'],
            'is_active' => true,
            'published_at' => now(),
        ]);

        FeatureSetting::query()
            ->where('feature_code', 'cms_page')
            ->update(['is_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/chinh-sach/'.$published->slug)
            ->assertNotFound();
    }

    public function test_an_empty_page_is_writable_by_an_admin_and_still_empty_for_a_visitor(): void
    {
        /*
         * The cold start. Edit mode makes the page root contenteditable, but an
         * empty root is zero pixels tall and holds no text node, so there is
         * nowhere to click and the feature looks absent. One seeded paragraph
         * gives the caret somewhere to land.
         *
         * A visitor must not see it. A page nobody has written is empty, and a
         * placeholder sentence shown to the public would be invented content.
         */
        $page = Page::query()->create([
            'title' => ['vi' => 'Chính sách'],
            'slug' => 'chinh-sach',
            'published_html' => ['vi' => ''],
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/chinh-sach/chinh-sach')
            ->assertOk()
            ->assertSee('Nhập nội dung trang tại đây.')
            ->assertSee('data-client-editable-root', false);

        $this->app['auth']->logout();

        $this->get('/chinh-sach/chinh-sach')
            ->assertOk()
            ->assertDontSee('Nhập nội dung trang tại đây.')
            ->assertDontSee('data-client-editable-root', false);

        // Rendering the seed writes nothing: only a real editing event marks the
        // page dirty, and only a dirty page is saved.
        $this->assertSame('', $page->fresh()->getTranslation('published_html', 'vi', false));
        $this->assertCount(0, $page->fresh()->revisions);
    }

    public function test_a_page_that_has_content_never_shows_the_seed(): void
    {
        $page = $this->publishedPage();

        $this->actingAs($this->admin)
            ->get('/chinh-sach/gioi-thieu')
            ->assertOk()
            ->assertSee('Trang giới thiệu')
            ->assertDontSee('Nhập nội dung trang tại đây.');

        $this->assertNotSame('', $page->getTranslation('published_html', 'vi', false));
    }

    public function test_the_storefront_history_list_is_behind_the_same_gate_as_the_inline_save(): void
    {
        $page = $this->publishedPage();

        $this->getJson("/vi/admin/pages/{$page->id}/revisions")->assertUnauthorized();

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)
            ->getJson("/vi/admin/pages/{$page->id}/revisions")
            ->assertForbidden();

        $this->app['auth']->logout();

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<h1>Bản mới</h1>',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson("/vi/admin/pages/{$page->id}/revisions")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.revisions')
            ->assertJsonPath('data.revisions.0.has_locale', true);
    }

    public function test_rolling_back_from_the_storefront_only_touches_the_locale_being_viewed(): void
    {
        /*
         * The storefront editor shows one language. Restoring the others from
         * there would revert translations nobody on that screen can see, which
         * is the same hazard the site-block history already guards against.
         * The admin metadata form keeps restoring the whole record: that screen
         * owns all of it.
         */
        $page = $this->publishedPage();

        $this->actingAs($this->admin);

        foreach (['vi' => '<h1>VI mới</h1>', 'en' => '<h1>EN mới</h1>'] as $locale => $html) {
            $this->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => $locale,
                'published_html' => $html,
            ])->assertOk();
        }

        $oldest = $page->revisions()->orderBy('created_at')->orderBy('id')->firstOrFail();

        $this->postJson("/vi/admin/pages/{$page->id}/revisions/{$oldest->id}/restore")
            ->assertOk()
            ->assertJsonPath('success', true);

        $page->refresh();

        $this->assertSame('<main><h1>Trang giới thiệu</h1></main>', $page->getTranslation('published_html', 'vi', false));
        // Untouched: the rollback was asked for in Vietnamese.
        $this->assertSame('<h1>EN mới</h1>', $page->getTranslation('published_html', 'en', false));
    }

    public function test_a_rollback_refuses_a_snapshot_that_never_held_this_locale(): void
    {
        // An empty string is a real stored value meaning "hidden on purpose".
        // Writing one because the snapshot had nothing to say would be a
        // different instruction from the one the admin gave.
        $page = Page::query()->create([
            'title' => ['vi' => 'Điều khoản'],
            'slug' => 'dieu-khoan',
            'published_html' => ['vi' => '<p>Bản đầu</p>'],
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'published_html' => '<p>Bản hai</p>',
            ])->assertOk();

        $revision = $page->revisions()->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson("/vi/admin/pages/{$page->id}/revisions/{$revision->id}/restore")
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/en/admin/pages/{$page->id}/revisions/{$revision->id}/restore")
            ->assertStatus(422)
            ->assertJsonValidationErrors('revision');
    }

    private function publishedPage(): Page
    {
        return Page::query()->create([
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About us'],
            'slug' => 'gioi-thieu',
            'published_html' => [
                'vi' => '<main><h1>Trang giới thiệu</h1></main>',
                'en' => '<main><h1>About us</h1></main>',
            ],
            'is_active' => true,
            'published_at' => now(),
        ]);
    }

    public function test_admin_form_saves_policy_content_for_the_submitted_locale_only(): void
    {
        $this->actingAs($this->admin);
        $this->post('/vi/admin/pages', $this->payload())->assertRedirect();
        $page = Page::query()->firstOrFail();
        $page->update(['published_html' => ['vi' => '<section class="hero"><h1>Cũ</h1></section>', 'en' => '<p>English</p>']]);

        $updated = $this->payload();
        $updated['published_html'] = ['vi' => '<h2>Chính sách đổi trả</h2><p onclick="x()">Trong 7 ngày.</p><script>alert(1)</script>'];
        $this->put("/vi/admin/pages/{$page->id}", $updated)->assertRedirect();

        $page->refresh();
        $this->assertSame('<h2>Chính sách đổi trả</h2><p>Trong 7 ngày.</p>', $page->getTranslation('published_html', 'vi', false));
        $this->assertSame('<p>English</p>', $page->getTranslation('published_html', 'en', false));
    }

    public function test_admin_form_save_without_touching_the_editor_keeps_stored_content(): void
    {
        $this->actingAs($this->admin);
        $this->post('/vi/admin/pages', $this->payload())->assertRedirect();
        $page = Page::query()->firstOrFail();
        $page->update(['published_html' => ['vi' => '<section class="hero"><h1>Giữ nguyên</h1></section>']]);

        $this->put("/vi/admin/pages/{$page->id}", $this->payload())->assertRedirect();

        $this->assertSame('<section class="hero"><h1>Giữ nguyên</h1></section>', $page->fresh()->getTranslation('published_html', 'vi', false));
    }

    public function test_admin_form_can_empty_a_locale(): void
    {
        $this->actingAs($this->admin);
        $this->post('/vi/admin/pages', $this->payload())->assertRedirect();
        $page = Page::query()->firstOrFail();
        $page->update(['published_html' => ['vi' => '<p>Có nội dung</p>']]);

        $updated = $this->payload();
        $updated['published_html'] = ['vi' => ''];
        $this->put("/vi/admin/pages/{$page->id}", $updated)->assertRedirect();

        $this->assertSame('', $page->fresh()->getTranslation('published_html', 'vi', false));
    }

    public function test_page_form_renders_the_content_editor(): void
    {
        $this->actingAs($this->admin)
            ->get('/vi/admin/pages/create')
            ->assertOk()
            ->assertSee('name="published_html[vi]"', false)
            ->assertSee('quill.min.js', false)
            ->assertDontSee('page-open-inline-editor', false);
    }

    public function test_old_pages_path_redirects_permanently_to_policy_path(): void
    {
        $this->get('/pages/chinh-sach-bao-mat')
            ->assertStatus(301)
            ->assertRedirect('/chinh-sach/chinh-sach-bao-mat');
    }

    private function payload(): array
    {
        return [
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'slug' => ['vi' => 'gioi-thieu', 'en' => 'about'],
            'meta_title' => ['vi' => 'Giới thiệu'],
            'meta_description' => ['vi' => 'Thông tin về cửa hàng'],
            'is_active' => 1,
        ];
    }
}
