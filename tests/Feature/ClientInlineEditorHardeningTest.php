<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
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

        (new PermissionSeeder)->run();
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

        return $this->get('/chinh-sach/gioi-thieu')->assertOk()->getContent();
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

    public function test_text_regions_save_their_text_and_rich_regions_drop_browser_spans(): void
    {
        $html = $this->editorHtml();

        // innerHTML of a text region carries &amp;/&gt;, which the server stores
        // verbatim and Blade escapes again on the next render.
        $this->assertStringContainsString("return type === 'text' ? element.textContent : cleanEditedHtml(element);", $html);
        $this->assertStringContainsString('element.textContent = payload.data.value', $html);
        // Chrome's style-preserving <span>s survive the sanitizer as empty spans; an
        // un-bold must become a class or it reverts on reload.
        $this->assertStringContainsString('function cleanEditedHtml(element)', $html);
        $this->assertStringContainsString("classes.push('client-weight-normal')", $html);
    }

    public function test_paste_is_plain_text_and_enter_breaks_a_heading_line(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString("getData('text/plain')", $html);
        $this->assertStringContainsString("document.execCommand('insertText'", $html);
        $this->assertStringContainsString("document.execCommand('insertLineBreak')", $html);
    }

    /**
     * The click guard and the anti-uppercase rule name the panels by id: a panel
     * left out has its own buttons swallowed or its labels shouted.
     */
    public function test_every_editor_panel_is_spared_by_the_click_guard_and_the_theme_typography(): void
    {
        $html = $this->editorHtml();

        foreach (['#client-admin-bar', '#client-block-toolbar', '#client-outline', '#client-history', '#client-section-panel', '#client-viewport', '[id$="media-picker"]'] as $panel) {
            $this->assertStringContainsString("'{$panel}'", $html, "click guard spares {$panel}");
            $this->assertMatchesRegularExpression('/'.preg_quote($panel, '/').' \*[^{]*\{[^}]*text-transform: none !important/s', $html, "typography spares {$panel}");
        }
        // A region inside a link is edited, the link never followed.
        $this->assertStringContainsString("clickable.querySelector('[data-block-key]')", $html);
    }

    public function test_every_visitor_gets_the_formatting_reset_classes(): void
    {
        $this->get('/chinh-sach/gioi-thieu')->assertOk()
            ->assertSee('.client-weight-normal{font-weight:400!important}', false);
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

    public function test_the_open_region_is_tinted_without_repainting_the_theme(): void
    {
        $html = $this->editorHtml();

        $this->assertMatchesRegularExpression(
            '/\[data-block-key\]\[contenteditable="true"\]\s*\{[^}]*\}/',
            $html,
            'không tìm thấy rule tô vùng đang mở — test này không còn kiểm được gì',
        );

        preg_match('/\[data-block-key\]\[contenteditable="true"\]\s*\{([^}]*)\}/', $html, $rule);

        /*
         * `background` is a shorthand: it resets background-image to none and
         * replaces background-color, and with !important the theme cannot win
         * it back. A region with a picture or a dark fill therefore lost its
         * background the moment an editor opened it, while keeping the light
         * text that fill was there to carry. An inset shadow tints the same
         * area without touching either property.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/(?:^|[\s;])background\s*:/',
            $rule[1],
            'vùng đang sửa không được đặt background — dùng box-shadow: inset',
        );
        $this->assertStringContainsString('box-shadow: inset', $rule[1]);
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
        $html = $this->get('/chinh-sach/gioi-thieu')->assertOk()->getContent();

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

    /**
     * A section with an editable background is not a region. Clicks inside it reach the text,
     * images and links within, exactly as without it; the background opens only from its own
     * handle, which is editor chrome so a click on it never edits the page underneath.
     */
    public function test_a_background_opens_only_from_its_handle_and_never_takes_a_childs_click(): void
    {
        $html = $this->editorHtml();
        $intercept = substr($html, strpos($html, 'function interceptClicks('));
        // Up to the next function declared beside it: the whole click handler.
        $intercept = substr($intercept, 0, (int) strpos($intercept, '
            function ', 30));

        $this->assertStringNotContainsString('data-client-background', $intercept);
        $this->assertStringContainsString("let region = event.target.closest('[data-block-key]');", $intercept);
        $this->assertMatchesRegularExpression("/EDITOR_CHROME = \[[^\]]*'#client-bg-handle'/", $html);
        $this->assertStringContainsString("bgHandle.addEventListener('click'", $html);
        $this->assertStringContainsString('document.body.appendChild(bgHandle)', $html);
    }

    public function test_choosing_a_picture_rewrites_sources_instead_of_dropping_them(): void
    {
        $html = $this->editorHtml();

        // Dropping srcset let the browser fall back to <source> and show the theme's picture again.
        $this->assertStringNotContainsString("element.removeAttribute('srcset');\n                        element.src", $html);
        $this->assertStringContainsString('function setImagePicture(img, url)', $html);
        $this->assertStringContainsString('function setBackgroundPicture(', $html);
        // Cancel puts back exactly what the theme printed.
        $this->assertStringContainsString('snapshot.srcset', $html);
        $this->assertStringContainsString('snapshot.sources', $html);
    }

    /** "Ẩn vùng" left an empty dashed box on the page, before Lưu and after it. */
    public function test_a_hidden_region_leaves_the_page_at_once_and_stays_gone_after_saving(): void
    {
        $html = $this->editorHtml();

        // After Lưu the bar marks the region client-block-empty; that class must hide it while editing.
        $this->assertMatchesRegularExpression('/body\.client-edit-active \.client-block-empty\s*\{\s*display: none !important;/', $html);
        // Before Lưu: the clear action hides it the same way instead of only emptying it.
        $clear = substr($html, (int) strpos($html, "if (action === 'clear')"), 900);
        $this->assertStringContainsString("region.classList.add('client-block-empty')", $clear);
    }

    /** A reload ended edit mode after every copy. */
    public function test_duplicating_puts_the_copy_on_the_page_without_a_reload(): void
    {
        $html = $this->editorHtml();

        foreach (['async function duplicateRegion()', "if (action === 'duplicate') {"] as $start) {
            $body = substr($html, (int) strpos($html, $start), 2200);
            $this->assertStringContainsString('insertCopy(', $body, $start);
            $this->assertStringNotContainsString('location.reload', $body, $start);
        }
    }

    /** A copy of a span in a heading ran on after the original on the same line. */
    public function test_a_copy_of_an_inline_region_starts_its_own_line(): void
    {
        $html = $this->editorHtml();

        $this->assertStringContainsString('.client-copy-line{display:block!important}', $html);
        $this->assertStringContainsString("copy.classList.add('client-copy-line')", $html);
    }

    /** A theme's `<strong class="text-slate-800">` kept its own colour inside a coloured selection. */
    public function test_a_colour_reaches_bold_words_that_carry_the_theme_colour(): void
    {
        $html = $this->editorHtml();
        $apply = substr($html, (int) strpos($html, 'function applyColor(value)'), 1200);

        $this->assertStringContainsString('function enforceColor(region, value)', $html);
        $this->assertStringContainsString('if (value) enforceColor(active, value);', $apply);
    }

    public function test_an_admin_without_media_permission_gets_no_background_handle(): void
    {
        $html = $this->editorHtml(['pages.update']);

        $this->assertStringNotContainsString("bgHandle.id = 'client-bg-handle'", $html);
    }
}
