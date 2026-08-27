<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the properties that keep the inline editor working on an arbitrary
 * theme. Each assertion stands for a failure that has a specific cause; the
 * comments name it so a future edit does not quietly undo the fix.
 */
class ClientInlineEditorHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);

        $this->page = Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'],
            'slug' => 'gioi-thieu',
            'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function actingWith(array $permissions): User
    {
        $role = Role::query()->create([
            'name' => 'Role '.md5(implode(',', $permissions)),
            'permissions' => $permissions,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);

        return $user;
    }

    private function editorHtml(array $permissions = ['pages.update', 'media.view']): string
    {
        $this->actingWith($permissions);

        return $this->get('/vi/pages/gioi-thieu')->assertOk()->getContent();
    }

    public function test_the_editable_root_carries_a_theme_portable_hook(): void
    {
        // A cut theme renders page content in its own wrapper; it must be able
        // to opt in with an attribute instead of reproducing the id convention.
        $this->assertStringContainsString('data-client-editable-root', $this->editorHtml());
    }

    public function test_the_editor_falls_back_to_the_attribute_and_bails_out_without_a_root(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString("document.querySelector('[data-client-editable-root]')", $html);
        // Nothing editable is a normal outcome on a fully database-driven page.
        // Throwing there would abort the script, taking the media picker with it.
        $this->assertStringContainsString('if (window.clientBlocksCount() === 0) return;', $html);
    }

    public function test_clicks_are_intercepted_in_the_capture_phase(): void
    {
        // Theme lightboxes, tab switchers and inline onclick handlers run on the
        // way up. Listening on the content element lets the theme's modal open
        // on top of the media picker.
        $html = $this->editorHtml();

        $this->assertStringContainsString('}, true);', $html);
        $this->assertStringContainsString('stopImmediatePropagation', $html);
    }

    public function test_dirtiness_comes_from_editing_events_not_from_a_dom_diff(): void
    {
        $html = $this->editorHtml();

        // Sliders clone slides and lazy-loaders rewrite src. A diff against a
        // load-time snapshot would treat that as an edit and persist generated
        // markup into the stored page.
        $this->assertStringContainsString("['input', 'paste', 'cut', 'drop']", $html);
        $this->assertStringNotContainsString('lastSavedHtml', $html);
    }

    public function test_nothing_is_written_without_an_explicit_save(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString('client-inline-save-button', $html);
        $this->assertStringContainsString('client-inline-cancel-button', $html);
        // Leaving edit mode used to save silently, and blind unload saves
        // persisted accidental keystrokes with no way back.
        $this->assertStringNotContainsString("addEventListener('pagehide'", $html);
        $this->assertStringContainsString("addEventListener('beforeunload'", $html);
    }

    public function test_conditional_bar_buttons_ship_hidden_and_hidden_actually_hides(): void
    {
        $html = $this->editorHtml();

        // The bar styles its buttons with display !important, which outranks
        // the user agent's [hidden] rule unless this override exists.
        $this->assertStringContainsString('#client-admin-bar [hidden]', $html);
        $this->assertMatchesRegularExpression('/id="client-inline-save-button"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/id="client-inline-cancel-button"[^>]*hidden/', $html);
    }

    public function test_an_admin_without_media_permission_gets_no_picker_and_no_image_hook(): void
    {
        $html = $this->editorHtml(['pages.update']);

        $this->assertStringContainsString('interceptClicks(null)', $html);
        $this->assertStringNotContainsString('client-inline-media-picker', $html);
        $this->assertStringNotContainsString('media/resources', $html);
    }

    public function test_an_admin_with_media_permission_gets_the_picker_wired_to_images(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString('interceptClicks(function (img)', $html);
        $this->assertStringContainsString('client-inline-media-picker', $html);
    }

    public function test_guests_receive_no_editor_at_all(): void
    {
        $html = $this->get('/vi/pages/gioi-thieu')->assertOk()->getContent();

        foreach ([
            'client-admin-bar',
            'client-inline-edit-button',
            'client-inline-media-picker',
            'data-client-editable-root',
            'interceptClicks',
            // contenteditable="false" on the navigation header is deliberate
            // and inert; it is a rendering guard, not an editor hook.
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $html, "Guest nhìn thấy [{$marker}].");
        }
    }

    public function test_an_admin_without_the_page_permission_receives_no_editor(): void
    {
        $html = $this->editorHtml(['media.view']);

        $this->assertStringNotContainsString('client-admin-bar', $html);
        $this->assertStringNotContainsString('client-inline-media-picker', $html);
    }
}
