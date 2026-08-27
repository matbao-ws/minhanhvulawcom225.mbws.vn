<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The section settings panel as a shipped surface.
 *
 * What a browser has to confirm — that a drag feels right, that a colour preview
 * repaints — is listed in the manual checklist. What is asserted here is
 * everything that can go wrong without anyone clicking: who receives the markup,
 * who receives the libraries, and whether the panel still routes its writes
 * through the one owner of edit mode.
 */
class SectionPanelTest extends TestCase
{
    use RefreshDatabase;

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

    private function sandboxHtml(?User $user = null): string
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get('/vi/sandbox/inline-editor')->assertOk()->getContent();
    }

    private function panel(): string
    {
        return file_get_contents(resource_path('views/client/partials/inline-section-panel.blade.php'));
    }

    private function bar(): string
    {
        return file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));
    }

    public function test_a_guest_receives_neither_the_panel_nor_its_library(): void
    {
        $html = $this->sandboxHtml();

        foreach (['client-section-panel', 'clientSectionPanel', 'alpine.min.js', 'sortable.min.js'] as $marker) {
            $this->assertStringNotContainsString($marker, $html, "guest thấy {$marker}");
        }
    }

    public function test_a_customer_receives_neither_the_panel_nor_its_library(): void
    {
        // A customer is a user with no role; they authenticate on the same guard.
        $customer = User::factory()->create(['role_id' => null]);
        $html = $this->sandboxHtml($customer);

        $this->assertStringNotContainsString('client-section-panel', $html);
        $this->assertStringNotContainsString('alpine.min.js', $html);
    }

    public function test_an_authorized_admin_receives_the_panel_and_its_library(): void
    {
        $html = $this->sandboxHtml($this->editor());

        $this->assertStringContainsString('id="client-section-panel"', $html);
        $this->assertStringContainsString('alpine.min.js', $html);
        $this->assertFileExists(public_path('client-assets/vendor/alpine.min.js'));
    }

    public function test_the_panel_is_served_its_schema_rather_than_fetching_one(): void
    {
        /*
         * The panel needs labels and options to draw anything at all, and they cannot
         * change between page loads. Class names travel with them because previewing
         * a choice before Save means putting the class on the wrapper.
         */
        $html = $this->sandboxHtml($this->editor());

        $this->assertStringContainsString('client-section--bg-dark', $html);
        $this->assertStringContainsString('--client-section-accent', $html);
        $this->assertStringContainsString('"type":"token"', $html);
        $this->assertStringContainsString('"type":"color"', $html);

        // Labels ship too, in `@json`'s script-safe encoding: it escapes unicode and
        // the HTML-significant characters, which is exactly what makes it safe to
        // drop a PHP array into a <script> block.
        $this->assertStringContainsString(
            json_encode('Màu nền', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            $html,
        );
    }

    public function test_the_panel_queues_through_the_bar_instead_of_writing_itself(): void
    {
        /*
         * Edit mode has exactly one owner. A panel that called the endpoint on its
         * own would be a second one, and the two would disagree about what is still
         * pending the moment either failed.
         */
        $this->assertStringContainsString('bar.registerSaver(saver)', $this->panel());
        $this->assertStringContainsString('registerSaver: function (saver)', $this->bar());
        $this->assertStringContainsString('for (const saver of pendingSavers())', $this->bar());
        $this->assertStringContainsString('pendingSavers().forEach(', $this->bar());
    }

    public function test_the_panel_never_marks_the_page_body_dirty(): void
    {
        /*
         * `markDirty()` also drives `savePageBody()`. Reaching for it to make the Save
         * button appear would rewrite a CMS page's entire `published_html` because
         * somebody changed one section's background.
         */
        $this->assertStringNotContainsString('markDirty', $this->panel());
        $this->assertStringContainsString('api.refreshPending()', $this->panel());
    }

    public function test_pending_settings_wait_for_save_and_are_undone_by_cancel(): void
    {
        $panel = $this->panel();

        // Queued in a map the component does not own, so closing the panel cannot
        // discard work, and snapshotted once so Huỷ restores the loaded state.
        $this->assertStringContainsString('queued.set(wrapper, state);', $panel);
        $this->assertStringContainsString('if (!snapshots.has(wrapper)) {', $panel);
        $this->assertStringContainsString('wrapper.className = snapshot.className;', $panel);
    }

    public function test_the_panel_adopts_the_servers_answer_rather_than_its_own_guess(): void
    {
        // The same reason a region edit echoes back the sanitized value: a setting the
        // server refused or normalised has to be what the admin ends up looking at.
        $this->assertStringContainsString('adopt(wrapper, state.section, data);', $this->panel());
    }

    public function test_visibility_does_not_ride_on_alpines_x_show(): void
    {
        /*
         * `x-show` hides by writing `display: none` inline, and an inline declaration
         * loses to an author `!important` rule — which the panel's own `display` has
         * to be, or theme CSS wins and the panel ships unstyled.
         */
        $panel = $this->panel();

        // The directive, not the word: the comment above the CSS rule explains why
        // `x-show` is not used, and would otherwise fail this assertion.
        $this->assertStringNotContainsString('x-show=', $panel);
        $this->assertStringContainsString(":class=\"{ 'is-open': isOpen }\"", $panel);
        $this->assertMatchesRegularExpression('/#client-section-panel\.is-open\s*\{/', $panel);
    }

    public function test_the_panel_survives_starting_before_the_bar_exists(): void
    {
        /*
         * Alpine's CDN build starts itself in a microtask as soon as its script runs,
         * and `defer` places that before DOMContentLoaded — where the bar publishes
         * `window.clientBlocks`. So every component `init()` runs first.
         */
        $panel = $this->panel();

        $this->assertStringContainsString('function whenBarReady(callback)', $panel);
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', function () {", $panel);
        // And the registration sits below the object it registers: whenBarReady runs
        // synchronously when the bar is already up, so a forward reference would be a
        // TDZ error rather than a harmless undefined.
        $this->assertLessThan(
            strpos($panel, 'bar.registerSaver(saver)'),
            strpos($panel, 'const saver = {'),
        );
    }

    public function test_the_settings_control_lives_on_the_outline_row_not_on_the_page(): void
    {
        /*
         * A gear over every section the moment edit mode came on would add a
         * positioned child to every flex and grid container on the page and reflow all
         * of them — the same reason the "+" and "×" are single floating controls.
         */
        $outline = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        $this->assertStringContainsString("element.hasAttribute('data-section-configurable')", $outline);
        $this->assertStringContainsString('window.clientSectionPanel.open(element)', $outline);

        // And the page itself grows no control: the wrapper carries data attributes
        // only, which is what the panel reads its current state from.
        $html = $this->sandboxHtml($this->editor());
        $this->assertStringNotContainsString('client-section-gear', $html);
    }

    public function test_the_gear_reports_unsaved_settings_on_the_row(): void
    {
        // The panel holding them may be closed, and the row is then the only place
        // unsaved work shows.
        $outline = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        $this->assertStringContainsString('window.clientSectionPanel.isQueued(element)', $outline);
        $this->assertMatchesRegularExpression('/client-outline__gear\.is-queued\s*\{/', $outline);
    }

    public function test_leaving_edit_mode_closes_the_panel(): void
    {
        // Its controls would otherwise still work while the rest of the editor had
        // stood down.
        $this->assertStringContainsString("bar.on('mode', function (on) {", $this->panel());
    }

    public function test_the_panel_uses_inline_svg_and_no_icon_font(): void
    {
        // An arbitrary project's storefront may load no icon font, and a missing glyph
        // turns every button into an empty box.
        $panel = $this->panel();

        $this->assertStringContainsString('<svg viewBox="0 0 24 24"', $panel);
        foreach (['ti-', 'fa-', 'material-icons', 'bi-'] as $iconFont) {
            $this->assertStringNotContainsString($iconFont, $panel, "panel dùng icon font {$iconFont}");
        }
    }
    public function test_the_preview_frame_carries_no_second_editor(): void
    {
        /*
         * The preview loads this same URL in an iframe. Without suppression it would
         * contain a second admin bar, a second outline panel and a second owner of
         * edit mode, each believing it knew which region was open.
         */
        $html = $this->actingAs($this->editor())
            ->get('/vi/sandbox/inline-editor?client_chrome=off')
            ->assertOk()
            ->getContent();

        foreach ([
            'client-admin-bar',
            'client-outline',
            'client-section-panel',
            'client-viewport',
            'clientBlocks',
            'sortable.min.js',
            'alpine.min.js',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, $html, "khung xem trước vẫn có {$marker}");
        }
    }

    public function test_the_preview_frame_still_renders_the_page_itself(): void
    {
        // Suppressing the editor must not suppress the storefront. A preview of an
        // empty page would answer no question at all.
        $html = $this->actingAs($this->editor())
            ->get('/vi/sandbox/inline-editor?client_chrome=off')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Thử bộ công cụ sửa nội dung', $html);
        $this->assertStringContainsString('class="client-section"', $html);
    }

    public function test_the_chrome_flag_can_only_remove_the_editor_never_grant_it(): void
    {
        /*
         * The most important property of the flag: it is not an authorization
         * decision. No value of it may put the editor in front of somebody who
         * cannot edit, so a guest sees the same nothing either way.
         */
        foreach (['?client_chrome=off', '?client_chrome=on', '?client_chrome=1', ''] as $query) {
            $html = $this->get('/vi/sandbox/inline-editor'.$query)->assertOk()->getContent();

            $this->assertStringNotContainsString('client-admin-bar', $html, "guest thấy editor với {$query}");
            $this->assertStringNotContainsString('clientBlocks', $html, "guest thấy editor với {$query}");
        }

        // And for an editor, only the documented value suppresses anything.
        $html = $this->actingAs($this->editor())
            ->get('/vi/sandbox/inline-editor?client_chrome=on')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('client-admin-bar', $html);
    }

    public function test_the_preview_uses_a_real_viewport_rather_than_a_transform(): void
    {
        /*
         * Media queries resolve against the viewport, so a scaled page still reports
         * the desktop width and every breakpoint stays where it was. Only a frame of
         * the requested width gives a true answer.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-viewport.blade.php'));

        $this->assertStringContainsString("document.createElement('iframe')", $partial);
        $this->assertStringNotContainsString('zoom:', $partial);
        $this->assertStringNotContainsString('scale(', $partial);
        $this->assertStringContainsString('data-viewport-mode="desktop"', $partial);
        $this->assertStringContainsString('data-viewport-mode="tablet"', $partial);
        $this->assertStringContainsString('data-viewport-mode="mobile"', $partial);
        $this->assertStringContainsString('client-vp__stage is-desktop', $partial);
        $this->assertStringContainsString('.client-vp__stage.is-desktop { width: 100% !important; }', $partial);
        $this->assertStringContainsString("candidate.setAttribute('aria-pressed'", $partial);
        $this->assertStringContainsString("document.body.style.overflow = 'hidden';", $partial);
        $this->assertStringContainsString('client-vp__rail', $partial);
        $this->assertStringContainsString('right: 56px !important;', $partial);
        $this->assertStringContainsString('top: 0 !important;', $partial);
        $this->assertStringContainsString('bottom: 0 !important;', $partial);
        $this->assertStringContainsString('data-client-viewport-close', $partial);
        $this->assertStringContainsString('min-height: 0 !important;', $partial);
        $this->assertMatchesRegularExpression(
            '/<aside class="client-vp__rail"[\s\S]*class="client-vp__back"[\s\S]*<\/aside>/',
            $partial,
        );
        $this->assertSame(1, substr_count($partial, 'class="client-vp__back"'));
        $this->assertStringContainsString('scrollbar-width: thin !important;', $partial);
        $this->assertStringContainsString('stylePreviewScrollbar(frame);', $partial);
        $this->assertStringContainsString('data-client-preview-scrollbar', $partial);
        // Built on open and torn down on close: a stale preview of a page since
        // edited is worse than a moment's load.
        $this->assertStringContainsString("stage.innerHTML = '';", $partial);
    }
}
