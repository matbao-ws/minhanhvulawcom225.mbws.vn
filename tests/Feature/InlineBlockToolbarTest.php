<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\SiteList;
use App\Models\User;
use App\Services\SiteContentService;
use App\Services\SiteListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The formatting toolbar and the repeatable-region controls behind it.
 *
 * Two properties matter beyond "it works": the markup never reaches anyone who
 * cannot edit, and the toolbar does not become a second owner of edit mode.
 */
class InlineBlockToolbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\PermissionSeeder())->run();
        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);

        // A CMS page is the storefront route that renders the client layout.
        Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'],
            'slug' => 'gioi-thieu',
            'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function editor(): User
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    /** The sandbox is where the section-list demo lives; the CMS page has none. */
    private function sandboxHtml(User $user): string
    {
        return $this->actingAs($user)->get('/vi/sandbox/inline-editor')->assertOk()->getContent();
    }

    private function storefrontHtml(?User $user = null): string
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->get('/vi/pages/gioi-thieu')->assertOk()->getContent();
    }

    public function test_guests_never_receive_the_toolbar_markup(): void
    {
        $html = $this->storefrontHtml();

        foreach (['client-block-toolbar', 'client-block-append', 'client-media-picker', 'clientBlocks'] as $marker) {
            $this->assertStringNotContainsString($marker, $html, "guest thấy {$marker}");
        }
    }

    public function test_a_customer_never_receives_the_toolbar_markup(): void
    {
        // A customer is a user with no role; they authenticate on the same guard.
        $customer = User::factory()->create(['role_id' => null]);

        $this->assertStringNotContainsString('client-block-toolbar', $this->storefrontHtml($customer));
    }

    public function test_an_admin_without_pages_update_never_receives_the_toolbar(): void
    {
        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['orders.view']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->assertStringNotContainsString('client-block-toolbar', $this->storefrontHtml($admin));
    }

    public function test_an_editor_receives_the_toolbar_with_its_full_control_set(): void
    {
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringContainsString('id="client-block-toolbar"', $html);

        // Same control set as the design being matched.
        foreach (['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList',
                  'insertOrderedList', 'createLink', 'unlink', 'removeFormat', 'undo', 'redo'] as $command) {
            $this->assertStringContainsString('data-client-block-command="'.$command.'"', $html);
        }
        foreach (['clear', 'restore', 'delete'] as $action) {
            $this->assertStringContainsString('data-client-block-action="'.$action.'"', $html);
        }
        foreach (['default', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $format) {
            $this->assertStringContainsString('data-client-block-format="'.$format.'"', $html);
        }
    }

    public function test_toolbar_icons_do_not_depend_on_an_icon_font(): void
    {
        // The storefront of an arbitrary project may load no icon font; a missing
        // glyph would turn every button into a blank square.
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringNotContainsString('fa-solid', $html);
        $this->assertStringContainsString('class="client-ico"', $html);
    }

    public function test_the_toolbar_does_not_take_over_edit_mode(): void
    {
        // Edit mode has exactly one owner. The toolbar reads the bar's state and
        // reports changes back; it must not enable or disable it.
        $partial = file_get_contents(resource_path('views/client/partials/inline-blocks.blade.php'));

        $this->assertStringContainsString('window.clientBlocks', $partial);
        $this->assertStringNotContainsString('clientBlocksToggle', $partial);
        $this->assertStringNotContainsString("classList.add('client-edit-active')", $partial);
    }

    public function test_the_bar_sends_the_current_type_and_format_not_the_opening_snapshot(): void
    {
        /*
         * The regression that made formatting look broken. A region opens as
         * `text`; the toolbar turns it into `html` when you bold something. The
         * save used to send the type captured when the region was first touched,
         * so the server stripped every tag back out and the formatting vanished
         * the moment you pressed Lưu.
         */
        $bar = file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));

        $this->assertStringContainsString(
            "const type = element.getAttribute('data-block-type') || snapshot.type;",
            $bar,
        );
        $this->assertStringContainsString(
            "const format = element.getAttribute('data-block-format') || null;",
            $bar,
        );
        $this->assertStringNotContainsString('type: snapshot.type,', $bar);
    }

    public function test_formatting_a_plain_region_persists_the_markup(): void
    {
        // End to end over the wire: what the toolbar sends after a bold must come
        // back as markup, not as escaped text.
        $editor = $this->editor();

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-blocks', [
                'key' => 'dev.sandbox.title',
                'type' => SiteBlock::TYPE_HTML,
                'format' => null,
                'content_locale' => 'vi',
                'value' => 'Xin <strong>chào</strong>',
            ])
            ->assertOk();

        $stored = app(SiteContentService::class)->value('dev.sandbox.title');
        $this->assertStringContainsString('<strong>chào</strong>', $stored);
    }

    public function test_swapping_a_heading_hands_the_pending_edit_to_the_new_element(): void
    {
        // Changing the heading level replaces the wrapper, so the pending map has
        // to follow the new node or Lưu writes one detached from the page.
        $bar = file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));
        $toolbar = file_get_contents(resource_path('views/client/partials/inline-blocks.blade.php'));

        $this->assertStringContainsString('replaceRegion: function (previous, next)', $bar);
        $this->assertStringContainsString('dirtyRegions.set(next, snapshot);', $bar);
        $this->assertStringContainsString('api.replaceRegion(active, replacement)', $toolbar);
    }

    public function test_the_admin_bar_uses_the_same_icon_set_as_the_toolbar(): void
    {
        $html = $this->storefrontHtml($this->editor());

        // Six controls on the bar: dashboard, page settings, edit, save, cancel,
        // logout. Emoji glyphs render differently on every platform and did not
        // match the design being copied.
        $this->assertGreaterThanOrEqual(6, substr_count($html, 'class="client-ico"'));
        foreach (['⚙', '📝', '✎', '✓', '↺', '↪', '⬆'] as $emoji) {
            $this->assertStringNotContainsString($emoji, $html);
        }
    }

    public function test_the_heading_menu_opens_from_the_wrapper_the_stylesheet_targets(): void
    {
        /*
         * The rule that reveals the menu is
         * `.client-block-toolbar__heading.is-open .…__heading-menu`, so `is-open`
         * has to land on the wrapper. It was being toggled on the menu itself,
         * which matched no rule at all and left the dropdown dead.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-blocks.blade.php'));

        $this->assertStringContainsString("headingControl.classList.toggle('is-open')", $partial);
        $this->assertStringContainsString("headingControl.classList.remove('is-open')", $partial);
        $this->assertStringNotContainsString("headingMenu.classList.toggle('is-open')", $partial);

        // And the rule it depends on must still be the one shipped.
        $html = $this->storefrontHtml($this->editor());
        $this->assertStringContainsString(
            '.client-block-toolbar__heading.is-open .client-block-toolbar__heading-menu',
            $html,
        );
    }

    public function test_the_editor_chrome_uses_the_same_face_as_the_admin_panel(): void
    {
        // The bar sits on the storefront but belongs to the admin product, and
        // reading in a different typeface from /admin made it look bolted on.
        // Loaded by the partial itself, not pushed to a stack a replacement theme
        // layout may not declare.
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringContainsString('family=Quicksand', $html);
        $this->assertStringContainsString("'Quicksand'", $html);
        // Gilroy is a licensed face living in one project's theme; naming it here
        // would make the chrome render differently per host project.
        $this->assertStringNotContainsString('Gilroy', $html);
    }

    public function test_the_font_is_never_loaded_for_someone_who_cannot_edit(): void
    {
        // A visitor must not pay a third-party font request for admin chrome they
        // will never see.
        $this->assertStringNotContainsString('family=Quicksand', $this->storefrontHtml());
    }

    public function test_the_bar_palette_matches_the_design_it_was_copied_from(): void
    {
        // Asserted as concrete values because "looks the same" was claimed once
        // without diffing, and the Cancel button had been a translucent grey
        // against the orange it was supposed to be.
        $html = $this->storefrontHtml($this->editor());

        $expected = [
            '.client-admin-bar__edit' => '#5d87ff',
            '.client-admin-bar__edit.is-active' => '#087f5b',
            '.client-admin-bar__save' => '#087f5b',
            '.client-admin-bar__save:hover' => '#099268',
            '.client-admin-bar__cancel' => '#d9480f',
            '.client-admin-bar__cancel:hover' => '#e8590c',
            '.client-admin-bar__hidden' => '#495057',
            '.client-admin-bar__hidden.is-active' => '#d9480f',
        ];

        foreach ($expected as $selector => $colour) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($selector, '/').'\s*\{\s*background:\s*'.preg_quote($colour, '/').'/i',
                $html,
                "{$selector} không còn màu {$colour}",
            );
        }
    }

    public function test_the_outline_sidebar_lists_regions_and_ships_only_to_editors(): void
    {
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringContainsString('id="client-outline"', $html);
        $this->assertStringContainsString('id="client-outline-button"', $html);
        // Built from the DOM at open time, so the panel starts empty in the markup.
        $this->assertStringContainsString('id="client-outline-body"', $html);
    }

    public function test_a_guest_never_receives_the_outline_sidebar(): void
    {
        // Its own test on purpose: actingAs persists for the rest of a test, so a
        // "guest" request made after an authenticated one is still authenticated.
        $this->assertStringNotContainsString('client-outline', $this->storefrontHtml());
    }

    public function test_the_outline_panel_reads_as_a_light_surface(): void
    {
        // Locked as values: the panel was dark on a first pass, and "make it white"
        // is only half done if the text stays light against it.
        $html = $this->storefrontHtml($this->editor());

        $this->assertMatchesRegularExpression('/#client-outline\s*\{[^}]*background:\s*#ffffff/i', $html);
        $this->assertMatchesRegularExpression('/#client-outline\s*\{[^}]*color:\s*#172033/i', $html);
        // No leftover light-on-dark rules.
        $this->assertStringNotContainsString('color: #e5e7eb !important', $html);
    }

    public function test_sections_render_in_declaration_order_before_anyone_edits(): void
    {
        // No stored row means the template's own order stands, so a fresh install
        // renders the approved design with nothing seeded.
        $html = $this->sandboxHtml($this->editor());

        $intro = strpos($html, 'data-section-name="intro"');
        $features = strpos($html, 'data-section-name="features"');
        $contact = strpos($html, 'data-section-name="contact"');

        $this->assertNotFalse($intro);
        $this->assertLessThan($features, $intro);
        $this->assertLessThan($contact, $features);
        $this->assertDatabaseCount('site_lists', 0);
    }

    public function test_reordering_a_parent_list_moves_the_sections(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'dev.sections',
                'order' => ['contact', 'intro', 'features'],
                'defaults' => ['intro', 'features', 'contact'],
            ])
            ->assertOk();

        $html = $this->actingAs($editor)->get('/vi/sandbox/inline-editor')->getContent();
        $this->assertLessThan(strpos($html, 'data-section-name="intro"'), strpos($html, 'data-section-name="contact"'));
    }

    public function test_a_child_section_cannot_be_moved_into_another_parent(): void
    {
        /*
         * The scoping property. Children live under their own list key, and a list
         * only accepts a permutation of the ids it already owns, so naming a
         * sibling's child is rejected outright rather than relocating it.
         */
        $this->actingAs($this->editor())
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'dev.sections',
                'order' => ['intro', 'features', 'contact', 'speed'],
                'defaults' => ['intro', 'features', 'contact'],
            ])
            ->assertOk();

        // The service keeps only ids the list already owns, so the child named from
        // outside is dropped rather than adopted.
        app()->forgetInstance(SiteListService::class);
        $stored = app(SiteListService::class)->items('dev.sections', ['intro', 'features', 'contact']);
        $this->assertNotContains('speed', $stored);
        $this->assertSame(['intro', 'features', 'contact'], $stored);
    }

    public function test_a_section_dropped_from_the_template_stops_rendering(): void
    {
        // The template is the source of what exists; a stale row naming a section
        // the Blade no longer declares must not resurrect it.
        app(SiteListService::class)->reorder('dev.sections', ['contact', 'intro', 'features'], ['intro', 'features', 'contact']);
        \App\Models\SiteList::query()->where('key', 'dev.sections')->update([
            'items' => ['contact', 'ghost', 'intro', 'features'],
        ]);
        app()->forgetInstance(SiteListService::class);

        $html = $this->sandboxHtml($this->editor());

        $this->assertStringNotContainsString('data-section-name="ghost"', $html);
        $this->assertStringContainsString('data-section-name="contact"', $html);
    }

    public function test_a_section_name_may_carry_a_hyphen_but_never_a_dot(): void
    {
        // A dot separates one list's key from the next; a name holding one could
        // address a different list's storage.
        $lists = app(SiteListService::class);

        $this->assertTrue($lists->isValidId('product-grid'));
        $this->assertFalse($lists->isValidId('home.sections'));
        $this->assertFalse($lists->isValidId('-leading'));
    }

    public function test_only_boxes_inside_a_list_get_reorder_controls(): void
    {
        /*
         * A region authored in Blade sits where the template puts it. Recording
         * that position in the database would be recording the layout — the page
         * builder this core deliberately does not have. Only list items move.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        // Movable only when the node belongs to a list; everything else is placed
        // by the template and gets neither a grip nor arrows.
        $this->assertStringContainsString('const movable = Boolean(listKey && itemId);', $partial);
        $this->assertStringContainsString('if (movable) addMoveControls(row, listKey, itemId);', $partial);
    }

    public function test_a_drag_is_refused_across_two_different_lists(): void
    {
        /*
         * The scoping rule as the drag layer enforces it: a row only accepts a drop
         * from a row carrying the same list key, and withholding preventDefault on a
         * foreign list is what makes the cursor refuse it. Without this a child
         * section could be dropped among another parent's children — placement, not
         * ordering.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        $this->assertSame(
            2,
            substr_count($partial, "row.dataset.listKey !== dragging.listKey) return;"),
            'cả dragover lẫn drop đều phải chặn danh sách khác',
        );
        // And the server refuses it a second time, so a crafted request cannot do
        // what the interface prevents.
        $this->assertStringContainsString('Thứ tự gửi lên không khớp danh sách hiện tại.', file_get_contents(app_path('Services/SiteListService.php')));
    }

    public function test_reordering_rearranges_the_page_without_a_reload(): void
    {
        /*
         * Committing the order and then re-rendering the panel from an unchanged
         * page snapped the row back to where it started, which read as the feature
         * being broken. The live nodes have to move too.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        $this->assertStringContainsString('function applyOrderToDom(listKey, order)', $partial);
        $this->assertStringContainsString('applyOrderToDom(listKey, data.data.order || order);', $partial);
        // Inserted before the following sibling, not appended: appending would drop
        // the run past whatever the template placed after it.
        $this->assertStringContainsString('const anchor = last.nextSibling;', $partial);
        $this->assertStringContainsString('parent.insertBefore(nodes.get(id), anchor);', $partial);
    }

    public function test_the_outline_row_does_not_print_the_storage_key(): void
    {
        // The key is plumbing. It stays in the title attribute for anyone who needs
        // it, and off the surface for everyone who does not.
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringNotContainsString('client-outline__key', $html);
    }

    public function test_arrow_buttons_survive_alongside_dragging(): void
    {
        // A drag is unreachable from the keyboard; the arrows are the whole
        // accessible path to reordering and must not be dropped for looking tidier.
        $partial = file_get_contents(resource_path('views/client/partials/inline-outline.blade.php'));

        $this->assertStringContainsString("client-outline__move", $partial);
        $this->assertStringContainsString("'Lên trên'", $partial);
        $this->assertStringContainsString("'Xuống dưới'", $partial);
    }

    public function test_editor_can_reorder_the_boxes_of_a_region(): void
    {
        $editor = $this->editor();
        $lists = app(SiteListService::class);

        $first = $this->actingAs($editor)->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])->json('data.item');
        $second = $this->actingAs($editor)->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])->json('data.item');
        $this->assertSame([$first, $second], $lists->items('home.values.extra'));

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'home.values.extra',
                'order' => [$second, $first],
            ])
            ->assertOk()
            ->assertJsonPath('data.order', [$second, $first]);

        app()->forgetInstance(SiteListService::class);
        $this->assertSame([$second, $first], app(SiteListService::class)->items('home.values.extra'));
    }

    public function test_a_reorder_that_drops_an_item_is_refused(): void
    {
        // A stale tab must not be able to delete a box by omitting it from the
        // order it submits.
        $editor = $this->editor();
        $first = $this->actingAs($editor)->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])->json('data.item');
        $this->actingAs($editor)->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra']);

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-lists/items/order', [
                'key' => 'home.values.extra',
                'order' => [$first],
            ])
            ->assertStatus(422);
    }

    public function test_reordering_requires_the_content_permission(): void
    {
        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['orders.view']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->patchJson('/vi/admin/site-lists/items/order', ['key' => 'home.values.extra', 'order' => ['abc123']])
            ->assertForbidden();
    }

    public function test_the_bar_can_be_collapsed_into_a_corner_puck(): void
    {
        $html = $this->storefrontHtml($this->editor());
        $bar = file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));

        $this->assertStringContainsString('id="client-admin-bar-collapse"', $html);
        $this->assertStringContainsString('id="client-admin-bar-restore"', $html);
        $this->assertStringContainsString('#client-admin-bar.is-collapsed', $html);
        $this->assertStringContainsString('position: fixed !important', $html);

        /*
         * The puck hides through the `hidden` attribute, the same mechanism the
         * Save and Cancel buttons in this bar already use. An invented second
         * mechanism is what left the bar collapsible with no way back.
         */
        $this->assertStringContainsString('#client-admin-bar-restore[hidden]', $html);
        $this->assertStringContainsString('restoreButton.hidden = !collapsed;', $bar);
        $this->assertStringNotContainsString("classList.toggle('is-visible'", $bar);

        // And collapsing is refused outright when the puck is missing, so the
        // toolbar can never be hidden with nothing to bring it back.
        $this->assertStringContainsString('if (collapsed && !restoreButton) return;', $bar);
    }

    public function test_the_collapsed_puck_still_warns_about_unsaved_work(): void
    {
        // Collapsed, Lưu is out of sight. Without this the only signal that edits
        // are pending disappears with the bar, and closing the tab drops them.
        $html = $this->storefrontHtml($this->editor());
        $bar = file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));

        $this->assertStringContainsString('#client-admin-bar-restore.is-pending', $html);
        $this->assertStringContainsString("restoreButton.classList.toggle('is-pending', hasPending())", $bar);
    }

    public function test_collapsing_works_on_a_page_with_nothing_editable(): void
    {
        /*
         * The editor bails out early when a page has no editable region, but the
         * bar still renders Quản trị and Đăng xuất there. The collapse wiring has
         * to sit before that bail-out or the button is dead on those pages.
         */
        $bar = file_get_contents(resource_path('views/client/partials/admin-bar.blade.php'));

        $wiring = strpos($bar, "collapseButton.addEventListener");
        $bailout = strpos($bar, 'if (window.clientBlocksCount() === 0) return;');

        $this->assertNotFalse($wiring);
        $this->assertNotFalse($bailout);
        $this->assertLessThan($bailout, $wiring, 'nút thu gọn được nối sau khi script đã thoát');
    }

    public function test_the_bar_offers_a_way_back_to_a_hidden_region(): void
    {
        // A hidden region renders nothing, so without this control the handle for
        // "restore the theme text" sits on an element that is not on the page.
        $html = $this->storefrontHtml($this->editor());

        $this->assertStringContainsString('id="client-inline-hidden-button"', $html);
        $this->assertStringContainsString('client-admin-bar__hidden', $html);
        $this->assertStringContainsString('body.client-reveal-hidden .client-block-empty', $html);
    }

    public function test_a_hidden_region_still_renders_a_handle_for_an_editor(): void
    {
        /*
         * End to end, because this is the only route back: the region must survive
         * in the DOM for an editor, carry the cleared marker the reveal rule keys
         * on, and be reachable by the toolbar's restore action.
         */
        $editor = $this->editor();
        app(SiteContentService::class)->updateLocale('dev.sandbox.hideable', SiteBlock::TYPE_TEXT, 'vi', '', $editor->id);
        app()->forgetInstance(SiteContentService::class);

        $html = $this->sandboxHtml($editor);

        $this->assertMatchesRegularExpression(
            '/data-block-key="dev\.sandbox\.hideable"[^>]*data-block-cleared="true"/',
            $html,
        );
        $this->assertStringContainsString('class="client-block-cleared"', $html);
    }

    public function test_a_hidden_region_is_gone_entirely_for_a_visitor(): void
    {
        $editor = $this->editor();
        app(SiteContentService::class)->updateLocale('dev.sandbox.hideable', SiteBlock::TYPE_TEXT, 'vi', '', $editor->id);
        app()->forgetInstance(SiteContentService::class);

        $html = $this->get('/vi/sandbox/inline-editor')->assertOk()->getContent();

        $this->assertStringNotContainsString('dev.sandbox.hideable', $html);
        $this->assertStringNotContainsString('client-block-cleared', $html);
    }

    public function test_restoring_and_appending_re_render_the_page(): void
    {
        /*
         * Both put back markup that only the Blade file holds — the theme's own
         * text, or a new box's shape. Neither the script nor the response carries
         * it, so patching the DOM cannot work and the action silently appeared to
         * do nothing.
         */
        $partial = file_get_contents(resource_path('views/client/partials/inline-blocks.blade.php'));

        $this->assertSame(2, substr_count($partial, 'window.location.reload();'));
    }

    public function test_editor_can_save_a_heading_format_and_it_survives_rendering(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)
            ->patchJson('/vi/admin/site-blocks', [
                'key' => 'home.hero.title',
                'type' => SiteBlock::TYPE_HTML,
                'format' => 'h2',
                'content_locale' => 'vi',
                'value' => '<strong>Xin chào</strong>',
            ])
            ->assertOk()
            ->assertJsonPath('data.format', 'h2');

        $this->assertSame('h2', app(SiteContentService::class)->format('home.hero.title'));
    }

    public function test_a_heading_format_is_refused_on_a_plain_text_region(): void
    {
        // A heading wrapper around an escaped label would silently promote that
        // label to HTML.
        $this->actingAs($this->editor())
            ->patchJson('/vi/admin/site-blocks', [
                'key' => 'home.hero.title',
                'type' => SiteBlock::TYPE_TEXT,
                'format' => 'h2',
                'content_locale' => 'vi',
                'value' => 'Xin chào',
            ])
            ->assertStatus(422);
    }

    public function test_an_unknown_heading_format_is_rejected(): void
    {
        $this->actingAs($this->editor())
            ->patchJson('/vi/admin/site-blocks', [
                'key' => 'home.hero.title',
                'type' => SiteBlock::TYPE_HTML,
                'format' => 'marquee',
                'content_locale' => 'vi',
                'value' => 'x',
            ])
            ->assertStatus(422);
    }

    public function test_editor_can_add_and_remove_a_box_in_a_repeatable_region(): void
    {
        $editor = $this->editor();

        $added = $this->actingAs($editor)
            ->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])
            ->assertOk()
            ->json('data.item');

        $this->assertContains($added, app(SiteListService::class)->items('home.values.extra'));

        // Content written into that box must go with it, or a re-issued id would
        // resurrect somebody else's text.
        app(SiteContentService::class)->updateLocale(
            app(SiteListService::class)->itemKey('home.values.extra', $added, 'text'),
            SiteBlock::TYPE_TEXT,
            'vi',
            'Nội dung ô thêm',
            $editor->id,
        );
        $this->assertDatabaseCount('site_blocks', 1);

        $this->actingAs($editor)
            ->deleteJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra', 'item' => $added])
            ->assertOk();

        app()->forgetInstance(SiteListService::class);
        $this->assertSame([], app(SiteListService::class)->items('home.values.extra'));
        $this->assertDatabaseCount('site_blocks', 0);
    }

    public function test_list_mutations_require_the_content_permission(): void
    {
        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['orders.view']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra', 'item' => 'abc123'])
            ->assertForbidden();
    }

    public function test_a_list_key_invented_by_the_client_is_rejected(): void
    {
        // Keys are authored in Blade; anything else is a client inventing structure.
        $this->actingAs($this->editor())
            ->postJson('/vi/admin/site-lists/items', ['key' => 'Home Values/../etc'])
            ->assertStatus(422);
    }

    public function test_the_sandbox_page_exposes_every_kind_of_editable_region(): void
    {
        $html = $this->actingAs($this->editor())->get('/vi/sandbox/inline-editor')->assertOk()->getContent();

        // Text, HTML, image and a region that accepts extra boxes — one of each,
        // so the toolbar can be exercised end to end.
        $this->assertStringContainsString('data-block-key="dev.sandbox.title"', $html);
        $this->assertStringContainsString('data-block-key="dev.sandbox.rich"', $html);
        $this->assertStringContainsString('data-block-type="image"', $html);
        $this->assertStringContainsString('data-append-list="dev.sandbox.card.extra"', $html);
        $this->assertStringContainsString('id="client-block-toolbar"', $html);
    }

    public function test_the_sandbox_ships_no_seeded_content(): void
    {
        // Its defaults live in Blade. A core reused across projects must not carry
        // demo rows into a customer's database.
        $this->actingAs($this->editor())->get('/vi/sandbox/inline-editor')->assertOk();

        $this->assertDatabaseCount('site_blocks', 0);
        $this->assertDatabaseCount('site_lists', 0);
    }

    public function test_a_region_cannot_grow_past_the_item_ceiling(): void
    {
        SiteList::query()->create([
            'key' => 'home.values.extra',
            'items' => array_map(fn (int $i): string => 'id'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, SiteListService::MAX_ITEMS)),
        ]);

        $this->actingAs($this->editor())
            ->postJson('/vi/admin/site-lists/items', ['key' => 'home.values.extra'])
            ->assertStatus(422);
    }
}
