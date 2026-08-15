<?php

namespace Tests\Feature;

use App\Contracts\TranslationProvider;
use App\Models\FeatureSetting;
use App\Models\Language;
use App\Models\Product;
use App\Models\Role;
use App\Models\TranslationRequest;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\LanguageRegistry;
use App\Services\LocalizedSlugService;
use App\Services\LocalizedContent;
use App\Services\MultilingualSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(
            ['feature_code' => 'catalog'],
            ['is_enabled' => true],
        );
    }

    protected function tearDown(): void
    {
        app(LanguageRegistry::class)->forget();
        parent::tearDown();
    }

    public function test_product_content_and_slugs_are_saved_and_resolved_per_locale(): void
    {
        $product = app(ProductService::class)->create([
            'name' => ['vi' => 'Máy tính xách tay', 'en' => 'Laptop Computer'],
            'slug' => ['vi' => 'may-tinh-xach-tay', 'en' => 'laptop-computer'],
            'price' => 1000000,
            'is_active' => true,
        ]);

        $this->assertSame('Máy tính xách tay', $product->getTranslation('name', 'vi', false));
        $this->assertSame('Laptop Computer', $product->getTranslation('name', 'en', false));
        $this->assertSame('may-tinh-xach-tay', $product->slug);
        $this->assertSame('laptop-computer', $product->canonicalSlug('en'));

        $this->getJson('/api/public/products/laptop-computer?locale=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('data.name', 'Laptop Computer')
            ->assertJsonPath('data.slug', 'laptop-computer')
            ->assertJsonPath('meta.locale', 'en');

        app(ProductService::class)->update($product, [
            'name' => ['vi' => 'Máy tính xách tay', 'en' => 'Portable Computer'],
            'slug' => ['vi' => 'may-tinh-xach-tay', 'en' => 'portable-computer'],
            'price' => 1000000,
            'is_active' => true,
        ]);

        $resolvedAlias = app(LocalizedSlugService::class)->find(Product::class, 'laptop-computer', 'en');
        $this->assertSame($product->id, $resolvedAlias?->id);
        $this->getJson('/api/public/products/laptop-computer?locale=en')
            ->assertOk()
            ->assertJsonPath('data.slug', 'portable-computer');
    }

    public function test_public_api_honors_explicit_locale_and_accept_language_headers(): void
    {
        Product::query()->create([
            'name' => ['vi' => 'Tên tiếng Việt', 'en' => 'English name'],
            'slug' => 'san-pham',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->getJson('/api/public/products?locale=vi')
            ->assertOk()
            ->assertHeader('Content-Language', 'vi')
            ->assertJsonPath('data.0.name', 'Tên tiếng Việt');

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->getJson('/api/public/products')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('data.0.name', 'English name');

        $this->getJson('/api/public/products?locale=ko')->assertUnprocessable();
    }

    public function test_translation_preview_is_authorized_audited_and_does_not_expose_provider_credentials(): void
    {
        $this->app->instance(TranslationProvider::class, new class implements TranslationProvider
        {
            public function translate(array $texts, string $sourceLocale, string $targetLocale, string $format = 'text'): array
            {
                return array_map(fn (string $text) => $format === 'html' ? '<p>Translated</p><script>bad()</script>' : 'Translated: '.$text, $texts);
            }

            public function configured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }
        });

        $role = Role::query()->create(['name' => 'Translator', 'permissions' => ['translations.use']]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->postJson('/vi/admin/translations/preview', [
            'source_locale' => 'vi',
            'target_locale' => 'en',
            'fields' => ['name' => 'Xin chào', 'description' => '<p>Nội dung</p>'],
            'formats' => ['name' => 'text', 'description' => 'html'],
        ])->assertOk()
            ->assertJsonPath('data.fields.name', 'Translated: Xin chào')
            ->assertJsonPath('data.fields.description', '<p>Translated</p>');

        $audit = TranslationRequest::query()->sole();
        $this->assertSame('succeeded', $audit->status);
        $this->assertSame('fake', $audit->provider);
        $this->assertNotSame('Xin chào', $audit->source_hash);
        $this->assertSame(mb_strlen('Xin chào') + mb_strlen('<p>Nội dung</p>'), $audit->character_count);
        $this->assertArrayNotHasKey('fields', $audit->getAttributes());
    }

    public function test_active_language_registry_controls_admin_locale_routes(): void
    {
        $this->get('/ko/admin/login')->assertNotFound();
        $this->get('/ko/admin')->assertNotFound();

        Language::query()->create([
            'code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文',
            'is_active' => true, 'sort_order' => 20,
        ]);
        app(LanguageRegistry::class)->forget();

        $this->get('/zh/admin/login')->assertOk();
        $this->getJson('/api/public/languages')->assertJsonFragment(['code' => 'zh']);
    }

    public function test_content_fallback_can_change_without_changing_default_or_breaking_admin_locale(): void
    {
        $role = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->put('/vi/admin/languages/preferences', [
                'default_locale' => 'vi',
                'fallback_locale' => 'en',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('languages', [
            'code' => 'vi',
            'is_default' => true,
            'is_content_fallback' => false,
        ]);
        $this->assertDatabaseHas('languages', [
            'code' => 'en',
            'is_default' => false,
            'is_content_fallback' => true,
        ]);

        $this->get('/vi/admin/login')->assertRedirect('/vi/admin');
        $this->get('/en/admin/login')->assertRedirect('/en/admin');
    }

    public function test_database_content_preferences_override_environment_in_every_translation_mode(): void
    {
        Language::query()->update([
            'is_default' => false,
            'is_content_fallback' => false,
        ]);
        Language::query()->where('code', 'vi')->update(['is_default' => true]);
        Language::query()->where('code', 'en')->update(['is_content_fallback' => true]);

        config()->set('multilingual.default_locale', 'en');
        config()->set('multilingual.fallback_locale', 'vi');
        config()->set('app.fallback_locale', 'vi');

        app(MultilingualSettings::class)->update([
            'enabled' => true,
            'mode' => MultilingualSettings::MODE_GTRANSLATE,
            'gtranslate' => ['target_locales' => ['en']],
        ]);
        $registry = app(LanguageRegistry::class);
        $registry->forget();

        $this->assertSame('vi', $registry->defaultLocale());
        $this->assertSame('en', $registry->fallbackLocale());
        $this->assertSame(['vi'], $registry->codes());

        $product = Product::query()->create([
            'name' => ['en' => 'Database fallback content'],
            'slug' => 'database-fallback-content',
            'price' => 100,
            'is_active' => true,
        ]);
        app()->setLocale('vi');

        $this->assertSame(
            'Database fallback content',
            app(LocalizedContent::class)->get($product, 'name'),
        );

        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertJsonPath('data.multilingual.source_locale', 'vi');
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_default_and_fallback_must_be_different_active_languages(): void
    {
        $role = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)
            ->from('/vi/admin/languages')
            ->put('/vi/admin/languages/preferences', [
                'default_locale' => 'en',
                'fallback_locale' => 'en',
            ])
            ->assertRedirect('/vi/admin/languages')
            ->assertSessionHasErrors('fallback_locale');

        $this->assertDatabaseHas('languages', ['code' => 'vi', 'is_default' => true]);
    }

    /**
     * Language management is no longer hard-wired to the superadmin, but a
     * blanket wildcard still does not reach it: `languages.*` has to be granted
     * on purpose, so an upgrade cannot widen access by itself.
     */
    public function test_content_language_management_requires_an_explicit_grant(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['*'],
        ]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/languages')
            ->assertForbidden();
        $this->actingAs($admin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertDontSee('Cấu hình đa ngôn ngữ');

        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $superadmin = User::factory()->create(['role_id' => $superadminRole->id]);

        $this->actingAs($superadmin)
            ->get('/vi/admin/languages')
            ->assertOk();
        $this->actingAs($superadmin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertSee('Cấu hình đa ngôn ngữ')
            ->assertDontSee('Chỉ Superadmin được thay đổi trạng thái và cơ chế dịch của dự án.');

        // Delegation works once the permission is granted deliberately.
        $translatorRole = Role::query()->create([
            'name' => 'Translator',
            'permissions' => ['languages.view', 'languages.update'],
        ]);

        $this->actingAs(User::factory()->create(['role_id' => $translatorRole->id]))
            ->get('/vi/admin/languages')
            ->assertOk();
    }
}
