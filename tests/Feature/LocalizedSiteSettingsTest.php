<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageRegistry;
use App\Services\MultilingualSettings;
use App\Services\SiteBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Home page SEO, head office address and branch names/addresses are written once per content
 * language (Settings), stored expand-only (LocalizedSiteSettings) and read in the visitor's language.
 */
class LocalizedSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'multilingual'], ['is_enabled' => true]);
        $this->forgetLanguages();

        $role = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['settings.view', 'settings.update'],
            'is_system' => false,
        ]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);

        ProjectSetting::query()->create(['setting_key' => 'shop_name', 'setting_value' => 'Shop']);
    }

    protected function tearDown(): void
    {
        $this->forgetLanguages();

        parent::tearDown();
    }

    public function test_public_settings_answer_in_the_request_language_and_fall_back_to_the_default(): void
    {
        $this->storeSettings();

        $vi = $this->getJson('/api/public/settings?locale=vi')->assertOk();
        $vi->assertJsonPath('data.shop_name', 'Shop')
            ->assertJsonMissingPath('data.shop_name_translations')
            ->assertJsonPath('data.seo.title', 'Cửa hàng đồ gỗ')
            ->assertJsonPath('data.contact.address', '1 Lê Lợi, Quận 1')
            ->assertJsonPath('data.contact.branches.0.name', 'Chi nhánh Hà Nội')
            ->assertJsonMissingPath('data.seo.translations')
            ->assertJsonMissingPath('data.contact.translations');

        $en = $this->getJson('/api/public/settings?locale=en')->assertOk();
        $en->assertJsonPath('meta.locale', 'en')
            ->assertJsonPath('data.shop_name', 'Wood Shop')
            ->assertJsonMissingPath('data.shop_name_translations')
            ->assertJsonPath('data.seo.title', 'Wooden furniture store')
            // No English description: the default language's text, not an empty one.
            ->assertJsonPath('data.seo.description', 'Đồ gỗ thủ công')
            ->assertJsonPath('data.contact.address', '1 Le Loi, District 1')
            ->assertJsonPath('data.contact.phone', '0900000000')
            ->assertJsonPath('data.contact.branches.0.name', 'Hanoi branch')
            ->assertJsonPath('data.contact.branches.0.address', '2 Tràng Tiền')
            ->assertJsonMissingPath('data.contact.branches.0.translations');
    }

    public function test_site_branding_reads_the_contact_in_the_current_language(): void
    {
        $this->storeSettings();

        app()->setLocale('en');
        $this->assertSame('1 Le Loi, District 1', app(SiteBranding::class)->current()['contact']['address']);
        $this->assertSame('Wood Shop', app(SiteBranding::class)->current()['name']);

        app()->forgetScopedInstances();
        app()->setLocale('vi');
        $this->assertSame('1 Lê Lợi, Quận 1', app(SiteBranding::class)->current()['contact']['address']);
        $this->assertSame('Shop', app(SiteBranding::class)->current()['name']);
    }

    public function test_settings_form_has_a_tab_per_content_language_and_saves_each_language(): void
    {
        $this->actingAs($this->admin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertSee('name="seo[translations][en][title]"', false)
            ->assertSee('name="contact[translations][en][address]"', false)
            ->assertSee('name="shop_name_translations[en]"', false)
            ->assertSee('data-site-locale-target="en"', false)
            // The shared translate button, limited to its block (data-i18n-scope).
            ->assertSee('data-target-locale="en"', false)
            ->assertSee('data-i18n-scope', false)
            ->assertSee('English');

        $this->actingAs($this->admin)
            ->post('/vi/admin/settings', $this->payload())
            ->assertRedirect();

        $seo = ProjectSetting::query()->where('setting_key', 'seo')->value('setting_value');
        $contact = ProjectSetting::query()->where('setting_key', 'contact')->value('setting_value');

        $this->assertSame('Cửa hàng', $seo['title']);
        $this->assertSame(['en' => ['title' => 'Store']], $seo['translations']);
        $this->assertSame('Shop', ProjectSetting::query()->where('setting_key', 'shop_name')->value('setting_value'));
        $this->assertSame(['en' => 'Shop EN'], ProjectSetting::query()->where('setting_key', 'shop_name_translations')->value('setting_value'));
        $this->assertSame('Địa chỉ', $contact['address']);
        $this->assertSame(['en' => ['address' => 'Address']], $contact['translations']);
        $this->assertSame(['en' => ['name' => 'Branch']], $contact['branches'][0]['translations']);
    }

    public function test_saving_with_multilingual_switched_off_keeps_the_other_languages(): void
    {
        $this->storeSettings();
        FeatureSetting::query()->where('feature_code', 'multilingual')->update(['is_enabled' => false]);
        $this->forgetLanguages();

        $this->actingAs($this->admin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertDontSee('name="seo[translations][en][title]"', false);

        $payload = $this->payload();
        unset($payload['shop_name_translations'], $payload['seo']['translations'], $payload['contact']['translations'], $payload['contact']['branches'][0]['translations']);
        $payload['contact']['branches'][0]['name'] = 'Chi nhánh Hà Nội';

        $this->actingAs($this->admin)->post('/vi/admin/settings', $payload)->assertRedirect();

        $seo = ProjectSetting::query()->where('setting_key', 'seo')->value('setting_value');
        $contact = ProjectSetting::query()->where('setting_key', 'contact')->value('setting_value');

        $this->assertSame('Wooden furniture store', $seo['translations']['en']['title']);
        $this->assertSame(['en' => 'Wood Shop'], ProjectSetting::query()->where('setting_key', 'shop_name_translations')->value('setting_value'));
        $this->assertSame('1 Le Loi, District 1', $contact['translations']['en']['address']);
        // Same branch (same default-language name): its English text stays with it.
        $this->assertSame('Hanoi branch', $contact['branches'][0]['translations']['en']['name']);
    }

    private function storeSettings(): void
    {
        ProjectSetting::query()->updateOrCreate(['setting_key' => 'shop_name_translations'], ['setting_value' => ['en' => 'Wood Shop']]);
        ProjectSetting::query()->updateOrCreate(['setting_key' => 'seo'], ['setting_value' => [
            'title' => 'Cửa hàng đồ gỗ',
            'description' => 'Đồ gỗ thủ công',
            'translations' => ['en' => ['title' => 'Wooden furniture store']],
        ]]);
        ProjectSetting::query()->updateOrCreate(['setting_key' => 'contact'], ['setting_value' => [
            'phone' => '0900000000',
            'address' => '1 Lê Lợi, Quận 1',
            'translations' => ['en' => ['address' => '1 Le Loi, District 1']],
            'branches' => [[
                'name' => 'Chi nhánh Hà Nội',
                'address' => '2 Tràng Tiền',
                'translations' => ['en' => ['name' => 'Hanoi branch']],
            ]],
        ]]);
    }

    private function payload(): array
    {
        return [
            'shop_name' => 'Shop',
            'shop_name_translations' => ['en' => 'Shop EN', 'xx' => 'Ignored'],
            'seo' => [
                'title' => 'Cửa hàng',
                'description' => '',
                'translations' => ['en' => ['title' => 'Store', 'description' => ''], 'xx' => ['title' => 'Ignored']],
            ],
            'contact' => [
                'phone' => '0900000000',
                'address' => 'Địa chỉ',
                'translations' => ['en' => ['address' => 'Address']],
                'branches' => [[
                    'name' => 'Chi nhánh',
                    'address' => '',
                    'translations' => ['en' => ['name' => 'Branch', 'address' => '']],
                ]],
            ],
        ];
    }

    private function forgetLanguages(): void
    {
        app(MultilingualSettings::class)->forget();
        app(LanguageRegistry::class)->forget();
    }
}
