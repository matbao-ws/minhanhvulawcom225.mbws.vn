<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PageBuilderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderTest extends TestCase
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
            'permissions' => ['manage_pages', 'manage_media'],
        ]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_page_routes_require_admin_permission(): void
    {
        $this->get('/vi/admin/pages')->assertRedirect('/vi/admin/login');

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)->get('/vi/admin/pages')->assertForbidden();
    }

    public function test_authorized_admin_can_render_page_builder_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/vi/admin/pages/create')
            ->assertOk()
            ->assertSee('page-builder-canvas', false)
            ->assertSee('page-builder-html-button', false)
            ->assertSee('Dán HTML/CSS')
            ->assertSee('page-builder-preview-button', false)
            ->assertSee('ti-file-text', false)
            ->assertSee('admin-assets/libs/grapesjs/grapes.min.js', false)
            ->assertSee('cssIcons:', false)
            ->assertSee('#page-builder-canvas .fa { font-family: FontAwesome !important; }', false)
            ->assertSee("editor.on('load', initializeBuilderContent)", false)
            ->assertSee('#page-builder-canvas iframe.gjs-frame', false)
            ->assertSee('min-height: 580px;', false)
            ->assertDontSee('openBuilderPreview();', false)
            ->assertDontSee('editor.loadProjectData(project ||', false);

        $this->assertFileExists(public_path('admin-assets/libs/grapesjs/grapes.min.js'));
        $this->assertFileExists(public_path('admin-assets/libs/grapesjs/grapes.min.css'));
        $this->assertFileExists(public_path('admin-assets/libs/font-awesome/css/font-awesome.min.css'));
    }

    public function test_admin_can_create_and_update_a_localized_page(): void
    {
        $this->actingAs($this->admin);

        $this->post('/vi/admin/pages', $this->payload())
            ->assertRedirect();

        $page = Page::query()->firstOrFail();
        $this->assertSame('Giới thiệu', $page->getTranslation('title', 'vi'));
        $this->assertTrue($page->is_active);
        $this->assertStringNotContainsString('<script', $page->getTranslation('published_html', 'vi'));
        $this->assertStringNotContainsString('onclick=', $page->getTranslation('published_html', 'vi'));
        $this->assertStringContainsString('data-page-block="hero"', $page->getTranslation('published_html', 'vi'));
        $this->assertStringContainsString('style="padding: 20px"', $page->getTranslation('published_html', 'vi'));
        $this->assertStringContainsString('<svg', $page->getTranslation('published_html', 'vi'));
        $this->assertStringContainsString('<path', $page->getTranslation('published_html', 'vi'));

        $updated = $this->payload();
        $updated['title']['vi'] = 'Giới thiệu mới';
        $this->put("/vi/admin/pages/{$page->id}", $updated)->assertRedirect();

        $this->assertSame('Giới thiệu mới', $page->fresh()->getTranslation('title', 'vi'));
        $this->assertCount(1, $page->revisions);
    }

    public function test_public_api_only_returns_published_pages(): void
    {
        $published = Page::query()->create([
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'slug' => 'gioi-thieu',
            'builder_data' => ['version' => 1, 'locales' => []],
            'published_html' => ['vi' => '<div>Xin chào</div>', 'en' => '<div>Hello</div>'],
            'published_css' => ['vi' => '.hero{color:red}', 'en' => '.hero{color:blue}'],
            'is_active' => true,
            'published_at' => now(),
        ]);
        Page::query()->create([
            'title' => ['vi' => 'Bản nháp'],
            'slug' => 'ban-nhap',
            'builder_data' => ['version' => 1, 'locales' => []],
            'is_active' => false,
        ]);

        $this->getJson('/api/public/pages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);

        $this->getJson('/api/public/pages/gioi-thieu?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.html', '<div>Xin chào</div>');
        $this->getJson('/api/public/pages/ban-nhap')->assertNotFound();
    }

    public function test_dangerous_css_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $payload = $this->payload();
        $payload['published_css']['vi'] = '@import "https://evil.example/style.css";';

        $this->post('/vi/admin/pages', $payload)
            ->assertSessionHasErrors('published_css');
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_page_builder_seeder_is_safe_and_idempotent(): void
    {
        $this->seed(PageBuilderSeeder::class);
        $this->seed(PageBuilderSeeder::class);

        $this->assertDatabaseCount('pages', 3);
        $this->assertSame(3, Page::query()->where('is_active', true)->count());
        $this->assertStringContainsString(
            'demo-page',
            Page::query()->where('slug', 'gioi-thieu')->firstOrFail()->getTranslation('published_html', 'vi'),
        );

        $page = Page::query()->where('slug', 'gioi-thieu')->firstOrFail();
        $this->actingAs($this->admin)
            ->get("/vi/admin/pages/{$page->id}/edit")
            ->assertOk()
            ->assertSee('demo-page', false);

        $this->get('/vi/admin/pages')
            ->assertOk()
            ->assertSee('/vi/pages/gioi-thieu', false)
            ->assertSee('Xem trang');
    }

    public function test_authorized_admin_can_preview_a_saved_page_in_requested_locale(): void
    {
        $this->seed(PageBuilderSeeder::class);
        $page = Page::query()->where('slug', 'gioi-thieu')->firstOrFail();

        $this->actingAs($this->admin)
            ->get("/vi/admin/pages/{$page->id}/preview?content_locale=en")
            ->assertOk()
            ->assertSee('Building a better shopping experience')
            ->assertSee('.demo-page', false);
    }

    public function test_published_client_page_only_shows_edit_bar_to_authorized_admin(): void
    {
        $this->seed(PageBuilderSeeder::class);
        $page = Page::query()->where('slug', 'gioi-thieu')->firstOrFail();
        $clientUrl = '/vi/pages/'.$page->canonicalSlug('vi');

        $this->get($clientUrl)
            ->assertOk()
            ->assertSee('demo-page', false)
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
            ->assertDontSee('client-inline-editor', false);

        $pageOnlyRole = Role::query()->create([
            'name' => 'Chỉ sửa trang',
            'permissions' => ['manage_pages'],
        ]);
        $pageOnlyAdmin = User::factory()->create(['role_id' => $pageOnlyRole->id]);
        $this->actingAs($pageOnlyAdmin)
            ->get($clientUrl)
            ->assertOk()
            ->assertSee('client-inline-editor', false)
            ->assertDontSee('client-inline-media-picker', false);

        $this->actingAs($this->admin)
            ->get($clientUrl)
            ->assertOk()
            ->assertSee('client-admin-bar', false)
            ->assertSee('Sửa trực tiếp')
            ->assertSee('client-inline-editor-canvas', false)
            ->assertSee('client-inline-editor-media-button', false)
            ->assertSee('client-inline-media-picker', false)
            ->assertSee('Thêm ảnh mới')
            ->assertSee('grid-auto-rows: 150px', false)
            ->assertSee('object-fit: contain', false)
            ->assertSee('mediaResourcesUrl', false)
            ->assertSee('const saveUrl =', false);
    }

    public function test_authorized_admin_can_save_a_page_from_client_inline_editor(): void
    {
        $this->seed(PageBuilderSeeder::class);
        $page = Page::query()->where('slug', 'gioi-thieu')->firstOrFail();
        $oldEnglishHtml = $page->getTranslation('published_html', 'en', false);

        $this->actingAs($this->admin)
            ->patchJson("/vi/admin/pages/{$page->id}/inline", [
                'content_locale' => 'vi',
                'builder_data' => [
                    'pages' => [['component' => '<section><h1>Nội dung mới</h1></section>']],
                    'styles' => [],
                ],
                'published_html' => '<section onclick="alert(1)"><h1>Nội dung mới</h1><script>alert(1)</script></section>',
                'published_css' => 'h1 { color: blue; }',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.html', '<section><h1>Nội dung mới</h1></section>');

        $page->refresh();
        $this->assertSame('<section><h1>Nội dung mới</h1></section>', $page->getTranslation('published_html', 'vi', false));
        $this->assertSame($oldEnglishHtml, $page->getTranslation('published_html', 'en', false));
        $this->assertSame('h1 { color: blue; }', $page->getTranslation('published_css', 'vi', false));
        $this->assertCount(1, $page->revisions);
    }

    public function test_customer_cannot_use_client_inline_update_endpoint(): void
    {
        $this->seed(PageBuilderSeeder::class);
        $page = Page::query()->where('slug', 'gioi-thieu')->firstOrFail();
        $payload = [
            'content_locale' => 'vi',
            'builder_data' => [],
            'published_html' => '<p>Không được phép</p>',
            'published_css' => '',
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
            'builder_data' => ['version' => 1, 'locales' => []],
            'published_html' => ['vi' => '<p>Chưa xuất bản</p>'],
            'is_active' => false,
        ]);

        $this->get('/vi/pages/'.$draft->slug)->assertNotFound();

        $published = Page::query()->create([
            'title' => ['vi' => 'Trang đang hoạt động'],
            'slug' => 'trang-dang-hoat-dong',
            'builder_data' => ['version' => 1, 'locales' => []],
            'published_html' => ['vi' => '<p>Đã xuất bản</p>'],
            'is_active' => true,
            'published_at' => now(),
        ]);

        FeatureSetting::query()
            ->where('feature_code', 'cms_page')
            ->update(['is_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/vi/pages/'.$published->slug)
            ->assertNotFound();
    }

    private function payload(): array
    {
        return [
            'title' => ['vi' => 'Giới thiệu', 'en' => 'About'],
            'slug' => ['vi' => 'gioi-thieu', 'en' => 'about'],
            'meta_title' => ['vi' => 'Giới thiệu'],
            'meta_description' => ['vi' => 'Thông tin về cửa hàng'],
            'builder_data' => [
                'version' => 1,
                'locales' => [
                    'vi' => ['pages' => [['component' => '<div>Xin chào</div>']]],
                    'en' => ['pages' => [['component' => '<div>Hello</div>']]],
                ],
            ],
            'published_html' => [
                'vi' => '<section data-page-block="hero" style="padding: 20px" onclick="alert(1)"><h1>Xin chào</h1><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path></svg><script>alert(1)</script></section>',
                'en' => '<section data-page-block="hero"><h1>Hello</h1></section>',
            ],
            'published_css' => ['vi' => '.hero{color:red}', 'en' => '.hero{color:blue}'],
            'is_active' => 1,
        ];
    }
}
