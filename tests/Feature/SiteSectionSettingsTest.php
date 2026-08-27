<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\SiteSection;
use App\Models\User;
use App\Services\SiteSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How a storefront section renders, and what an editor may change about it.
 *
 * The line this whole feature stands on: an editor picks among shapes the
 * template already knows how to render. They never write CSS, never move a
 * section out of its list, and never introduce an element the design lacked.
 */
class SiteSectionSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** The list key the sandbox declares its sections under. */
    private const LIST_KEY = 'dev.sections';

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();
        foreach (['catalog', 'cms_page', 'menu'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
    }

    private function editor(): User
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    /** The sandbox is where the section-list demo lives. */
    private function sandboxHtml(?User $user = null): string
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get('/vi/sandbox/inline-editor')->assertOk()->getContent();
    }

    /**
     * Drop the authenticated user so the next request is a real visitor.
     *
     * `actingAs()` sets the user on the resolved guard and the app is not rebuilt
     * between requests in one test, so without this a "guest" assertion made after
     * a write is still made as the editor — and would pass whatever the code did.
     */
    private function logout(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function sections(): SiteSectionService
    {
        // Scoped, so the request-level cache has to be dropped between checks.
        app(SiteSectionService::class)->forget();

        return app(SiteSectionService::class);
    }

    /** @param array<string, string|null> $settings */
    private function save(string $section, ?string $variant = null, array $settings = [], bool $visible = true): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY,
            'section' => $section,
            'variant' => $variant,
            'settings' => $settings,
            'is_visible' => $visible,
        ]);
    }

    public function test_an_untouched_section_renders_exactly_as_the_template_does(): void
    {
        /*
         * There are deliberately no defaults in the registry. If an untouched
         * section picked up a background class, turning this feature on would have
         * repainted every existing install — which is the opposite of "a fresh
         * install renders the approved design".
         */
        $html = $this->sandboxHtml();

        $this->assertStringContainsString('class="client-section"', $html);
        $this->assertStringNotContainsString('client-section--bg-', $html);
        $this->assertStringNotContainsString('client-section--pad-', $html);
        $this->assertStringNotContainsString('--client-section-accent', $html);
        $this->assertDatabaseCount('site_sections', 0);
    }

    public function test_saved_tokens_become_classes_on_the_wrapper(): void
    {
        $this->actingAs($this->editor());

        $this->save('intro', 'boxed', ['background' => 'dark', 'alignment' => 'center', 'spacing' => 'lg'])
            ->assertOk()
            ->assertJsonPath('data.variant', 'boxed');

        $html = $this->sandboxHtml($this->editor());

        foreach ([
            'client-section--bg-dark',
            'client-section--align-center',
            'client-section--pad-lg',
            'client-section--intro-boxed',
        ] as $class) {
            $this->assertStringContainsString($class, $html, "thiếu class {$class}");
        }
    }

    public function test_the_database_stores_tokens_and_never_a_css_class(): void
    {
        /*
         * The whole point of the token indirection: repainting
         * `client-section--bg-primary` must be a stylesheet change, not an UPDATE
         * across every row that ever chose it.
         */
        $this->actingAs($this->editor());
        $this->save('intro', 'boxed', ['background' => 'primary'])->assertOk();

        $row = SiteSection::query()->firstWhere('key', self::LIST_KEY.'.intro');

        $this->assertSame(['background' => 'primary'], $row->settings);
        $this->assertSame('boxed', $row->variant);
        $this->assertStringNotContainsString('client-section', json_encode($row->settings));
    }

    public function test_a_variant_reaches_the_section_partial(): void
    {
        // The partial decides what a variant means. Handing it only a class would
        // limit a variant to things CSS alone can express.
        $this->actingAs($this->editor());
        $this->save('features', 'grid')->assertOk();

        $this->assertSame('grid', $this->sections()->variant(self::LIST_KEY, 'features'));
        $this->assertStringContainsString(
            "['sectionVariant' => \$variant, 'sectionSettings' => \$settings]",
            file_get_contents(resource_path('views/client/components/section-list.blade.php')),
        );
    }

    public function test_a_custom_colour_rides_as_a_custom_property(): void
    {
        /*
         * A colour picker produces values nobody declared in advance, so it cannot
         * be a token and no stylesheet can hold a rule for it. The class only says
         * "start reading the property".
         */
        $this->actingAs($this->editor());
        $this->save('intro', null, ['accent' => '#82151b'])->assertOk();

        $html = $this->sandboxHtml($this->editor());

        $this->assertStringContainsString('client-section--accent', $html);
        $this->assertStringContainsString('--client-section-accent:#82151b', $html);
        $this->assertSame(
            ['accent' => '#82151b'],
            SiteSection::query()->firstWhere('key', self::LIST_KEY.'.intro')->settings,
        );
    }

    public function test_there_is_no_variant_meaning_as_the_template_does_it(): void
    {
        /*
         * Null already means "as authored", the same way an absent setting does.
         * A variant named `plain` or `stack` would be a second way to say the same
         * thing, and would force a stylesheet rule with nothing in it.
         */
        $registry = app(\App\Support\SectionRegistry::class);

        foreach ($registry->sections() as $section => $definition) {
            foreach (array_keys($definition['variants']) as $variant) {
                $this->assertNotContains(
                    $variant,
                    ['plain', 'default', 'none', 'normal', 'stack'],
                    "section {$section} khai báo variant {$variant} trùng nghĩa với null",
                );
            }
        }
    }

    public function test_a_variant_the_section_does_not_declare_is_rejected(): void
    {
        $this->actingAs($this->editor());

        $this->save('intro', 'nonexistent')->assertStatus(422)->assertJsonValidationErrors('variant');
        // `contact` declares no variants at all, so every variant is invalid there.
        $this->save('contact', 'boxed')->assertStatus(422)->assertJsonValidationErrors('variant');
    }

    public function test_a_setting_the_section_did_not_opt_into_is_rejected(): void
    {
        // `contact` opted into background and spacing only.
        $this->actingAs($this->editor());

        $this->save('contact', null, ['alignment' => 'center'])->assertStatus(422)->assertJsonValidationErrors('settings');
    }

    public function test_a_value_outside_the_declared_tokens_is_rejected(): void
    {
        $this->actingAs($this->editor());

        $this->save('intro', null, ['background' => 'chartreuse'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.background');
    }

    public function test_a_colour_that_is_not_a_six_digit_hex_is_rejected(): void
    {
        /*
         * This value reaches an inline style attribute. Six hex digits is the only
         * shape that cannot also carry a url(), a var() or an expression.
         */
        $this->actingAs($this->editor());

        foreach (['red', '#fff', 'rgb(1,2,3)', 'var(--x)', '#82151b;background:url(x)'] as $bad) {
            $this->save('intro', null, ['accent' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('settings.accent');
        }
    }

    public function test_a_section_with_no_registry_entry_offers_nothing(): void
    {
        /*
         * An undeclared section is a section with no settings — a legal state, the
         * same way a region with no `format` keeps the tag its template authored.
         * It must not be writable, and it must not advertise a panel.
         */
        $this->actingAs($this->editor());

        $this->patchJson('/vi/admin/site-sections', [
            'list_key' => 'stress.sections',
            'section' => 'stress-intro',
            'variant' => null,
            'settings' => [],
            'is_visible' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('section');

        $stress = $this->actingAs($this->editor())->get('/vi/sandbox/inline-editor-stress')->assertOk()->getContent();

        $this->assertStringContainsString('data-section-name="stress-intro"', $stress);
        // Every section on the stress page is undeclared, so the wrappers are all
        // there for reordering and not one of them offers a settings panel.
        //
        // Matched with its value: the outline's own script mentions the bare
        // attribute name on every page, so only `="1"` distinguishes markup that
        // actually carries the hook from JavaScript that looks for it.
        $this->assertStringNotContainsString('data-section-configurable="1"', $stress);
    }

    public function test_a_hidden_section_disappears_for_visitors_and_dims_for_editors(): void
    {
        $this->actingAs($this->editor());
        $this->save('contact', null, [], false)->assertOk();

        $this->logout();
        $guest = $this->sandboxHtml();
        $this->assertStringNotContainsString('data-section-name="contact"', $guest);
        $this->assertStringNotContainsString('dev.sections.contact.text', $guest);

        // An editor keeps the handle: a section nobody can see is a section nobody
        // can bring back.
        $editing = $this->sandboxHtml($this->editor());
        $this->assertStringContainsString('client-section--hidden', $editing);
        $this->assertStringContainsString('data-section-name="contact"', $editing);
    }

    public function test_clearing_every_override_removes_the_row(): void
    {
        // The row *is* the override, the same contract `site_blocks` follows.
        $this->actingAs($this->editor());

        $this->save('intro', 'boxed', ['background' => 'dark'])->assertOk();
        $this->assertDatabaseCount('site_sections', 1);

        $this->save('intro', null, [], true)->assertOk();
        $this->assertDatabaseCount('site_sections', 0);
    }

    public function test_restore_drops_the_override_and_reports_the_template_state(): void
    {
        $this->actingAs($this->editor());
        $this->save('intro', 'boxed', ['background' => 'dark'])->assertOk();

        $this->deleteJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY,
            'section' => 'intro',
        ])->assertOk()->assertJsonPath('data.variant', null)->assertJsonPath('data.classes', []);

        $this->assertDatabaseCount('site_sections', 0);
    }

    public function test_an_unchanged_save_writes_nothing_and_records_no_revision(): void
    {
        $this->actingAs($this->editor());

        $this->save('intro', 'boxed', ['background' => 'dark'])->assertOk();
        $this->save('intro', 'boxed', ['background' => 'dark'])->assertOk();

        // First save of a brand-new row has nothing to snapshot; the second changed
        // nothing, so there is still nothing to record.
        $this->assertDatabaseCount('site_section_revisions', 0);
    }

    public function test_revisions_are_coalesced_within_ten_minutes(): void
    {
        // A settings panel fires on every click. One revision per click is noise,
        // not history.
        $this->actingAs($this->editor());

        $this->save('intro', 'boxed', ['background' => 'dark'])->assertOk();
        $this->save('intro', 'boxed', ['background' => 'muted'])->assertOk();
        $this->save('intro', null, ['background' => 'light'])->assertOk();

        $this->assertDatabaseCount('site_section_revisions', 1);

        $this->travel(11)->minutes();
        $this->save('intro', 'boxed', ['background' => 'primary'])->assertOk();

        $this->assertDatabaseCount('site_section_revisions', 2);
    }

    public function test_a_retired_token_stops_applying_instead_of_resolving_from_the_row(): void
    {
        /*
         * Reads are guarded as well as writes. A token dropped from the registry
         * must stop taking effect immediately — leaving it live would mean the
         * registry is no longer the source of truth it claims to be.
         */
        SiteSection::query()->create([
            'key' => self::LIST_KEY.'.intro',
            'variant' => 'retired-variant',
            'settings' => ['background' => 'retired-token', 'alignment' => 'center'],
            'is_visible' => true,
        ]);

        $this->assertNull($this->sections()->variant(self::LIST_KEY, 'intro'));
        $this->assertSame(['alignment' => 'center'], $this->sections()->settings(self::LIST_KEY, 'intro'));
    }

    public function test_guests_and_customers_cannot_change_how_a_section_renders(): void
    {
        $payload = [
            'list_key' => self::LIST_KEY,
            'section' => 'intro',
            'variant' => 'boxed',
            'settings' => [],
            'is_visible' => true,
        ];

        $this->patchJson('/vi/admin/site-sections', $payload)->assertUnauthorized();
        $this->deleteJson('/vi/admin/site-sections', $payload)->assertUnauthorized();

        // A customer is a user with no role; they authenticate on the same guard.
        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->patchJson('/vi/admin/site-sections', $payload)->assertForbidden();

        $this->assertDatabaseCount('site_sections', 0);
    }

    public function test_an_admin_without_pages_update_cannot_change_a_section(): void
    {
        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['pages.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));

        $this->save('intro', 'boxed')->assertForbidden();
    }

    public function test_a_guest_receives_no_section_settings_hook(): void
    {
        $html = $this->sandboxHtml();

        foreach (['data-section-configurable', 'data-section-variant', 'data-section-settings'] as $hook) {
            $this->assertStringNotContainsString($hook, $html, "guest thấy {$hook}");
        }
    }

    public function test_an_authorized_admin_receives_the_section_settings_hook(): void
    {
        $html = $this->sandboxHtml($this->editor());

        $this->assertStringContainsString('data-section-configurable="1"', $html);
        $this->assertStringContainsString('data-section-settings=', $html);
    }
}
