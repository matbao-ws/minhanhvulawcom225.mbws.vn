<?php

namespace Tests\Feature;

use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageRegistry;
use App\Services\MultilingualSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class MultilingualSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(MultilingualSettings::class)->forget();
        app(LanguageRegistry::class)->forget();

        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $adminRole = Role::query()->create([
            'name' => 'Admin',
            'permissions' => ['settings.view', 'settings.update', 'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete', 'payments.view', 'payments.create', 'payments.update', 'payments.delete', 'translations.use'],
            'is_system' => false,
        ]);

        $this->superadmin = User::factory()->create(['role_id' => $superadminRole->id]);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        ProjectSetting::query()->create([
            'setting_key' => 'shop_name',
            'setting_value' => 'Multilingual Shop',
        ]);
    }

    protected function tearDown(): void
    {
        app(MultilingualSettings::class)->forget();
        app(LanguageRegistry::class)->forget();

        parent::tearDown();
    }

    public function test_only_superadmin_can_see_and_change_multilingual_settings(): void
    {
        $this->actingAs($this->admin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertDontSee('Cấu hình đa ngôn ngữ');

        $this->actingAs($this->admin)
            ->post('/vi/admin/settings', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('project_settings', ['setting_key' => 'multilingual']);

        $this->actingAs($this->superadmin)
            ->get('/vi/admin/settings')
            ->assertOk()
            ->assertSee('Cấu hình đa ngôn ngữ')
            ->assertDontSee('Cấu hình Giao diện');
    }

    public function test_superadmin_can_select_gtranslate_and_public_api_exposes_widget_configuration(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson('/vi/admin/settings', $this->payload());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect_url', url('/vi/admin/settings'));

        $stored = ProjectSetting::query()->where('setting_key', 'multilingual')->firstOrFail()->setting_value;
        $this->assertTrue($stored['enabled']);
        $this->assertSame('gtranslate', $stored['mode']);
        $this->assertSame(['en'], $stored['gtranslate']['target_locales']);

        app(MultilingualSettings::class)->forget();
        app(LanguageRegistry::class)->forget();

        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertJsonPath('data.multilingual.enabled', true)
            ->assertJsonPath('data.multilingual.mode', 'gtranslate')
            ->assertJsonPath('data.multilingual.widget.script_url', 'https://cdn.gtranslate.net/widgets/latest/float.js')
            ->assertJsonPath('data.multilingual.widget.settings.default_language', 'vi')
            ->assertJsonPath('data.multilingual.widget.settings.languages.0', 'vi')
            ->assertJsonPath('data.multilingual.widget.settings.languages.1', 'en');

        $this->assertSame(['vi'], app(LanguageRegistry::class)->codes());
        $this->assertSame(['vi', 'en'], app(LanguageRegistry::class)->adminCodes());
        $this->get('/en/admin')->assertOk();

        $this->actingAs($this->superadmin)
            ->postJson('/vi/admin/translations/preview', [
                'source_locale' => 'vi',
                'target_locale' => 'en',
                'fields' => ['name' => 'Xin chào'],
            ])
            ->assertStatus(409);

        $widget = Blade::render('<x-gtranslate-widget />');
        $this->assertStringContainsString('gtranslate_wrapper', $widget);
        $this->assertStringContainsString('https://cdn.gtranslate.net/widgets/latest/float.js', $widget);
    }

    public function test_manual_mode_keeps_content_language_tabs_and_does_not_publish_a_widget(): void
    {
        $this->assertSame(['vi', 'en'], app(LanguageRegistry::class)->codes());

        $this->getJson('/api/public/settings')
            ->assertOk()
            ->assertJsonPath('data.multilingual.enabled', true)
            ->assertJsonPath('data.multilingual.mode', 'manual')
            ->assertJsonPath('data.multilingual.widget', null);

        $this->assertSame('', trim(Blade::render('<x-gtranslate-widget />')));
    }

    public function test_all_gtranslate_widget_looks_resolve_correct_script_urls(): void
    {
        $map = [
            'float' => 'https://cdn.gtranslate.net/widgets/latest/float.js',
            'dropdown_with_flags' => 'https://cdn.gtranslate.net/widgets/latest/dwf.js',
            'flags_dropdown' => 'https://cdn.gtranslate.net/widgets/latest/fd.js',
            'dropdown' => 'https://cdn.gtranslate.net/widgets/latest/dropdown.js',
            'flags' => 'https://cdn.gtranslate.net/widgets/latest/flags.js',
            'flags_name' => 'https://cdn.gtranslate.net/widgets/latest/fn.js',
            'flags_code' => 'https://cdn.gtranslate.net/widgets/latest/fc.js',
            'lang_names' => 'https://cdn.gtranslate.net/widgets/latest/ln.js',
            'lang_codes' => 'https://cdn.gtranslate.net/widgets/latest/lc.js',
            'globe' => 'https://cdn.gtranslate.net/widgets/latest/globe.js',
            'popup' => 'https://cdn.gtranslate.net/widgets/latest/popup.js',
            'popup_search' => 'https://cdn.gtranslate.net/widgets/latest/ps.js',
            'uswds' => 'https://cdn.gtranslate.net/widgets/latest/uswds.js',
        ];

        foreach ($map as $look => $expectedUrl) {
            $payload = $this->payload();
            $payload['multilingual']['gtranslate']['widget_look'] = $look;

            $this->actingAs($this->superadmin)
                ->postJson('/vi/admin/settings', $payload)
                ->assertOk();

            $service = app(MultilingualSettings::class);
            $service->forget();

            $this->assertSame($expectedUrl, $service->widgetScriptUrl());
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'shop_name' => 'Multilingual Shop',
            'multilingual' => [
                'enabled' => true,
                'mode' => 'gtranslate',
                'gtranslate' => [
                    'target_locales' => ['en'],
                    'widget_look' => 'float',
                    'position' => 'bottom_right',
                    'detect_browser_language' => false,
                    'native_language_names' => true,
                ],
            ],
        ];
    }
}
