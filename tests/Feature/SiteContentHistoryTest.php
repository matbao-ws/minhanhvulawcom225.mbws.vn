<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Language;
use App\Models\Role;
use App\Models\SiteBlock;
use App\Models\SiteBlockRevision;
use App\Models\SiteSection;
use App\Models\SiteSectionRevision;
use App\Models\User;
use App\Services\SiteContentService;
use App\Services\SiteSectionService;
use App\Support\SectionRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading the saved history, and getting back to one entry of it.
 *
 * The revisions were being written from the day inline editing shipped — coalesced
 * into ten-minute checkpoints — and nothing read them. These are the tests for the
 * reader, and for the one property that makes an immediate rollback acceptable:
 * the value being replaced is checkpointed first, so a rollback is itself undoable.
 */
class SiteContentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const LIST_KEY = 'dev.sections';

    protected function setUp(): void
    {
        parent::setUp();

        (new PermissionSeeder)->run();
        foreach (['catalog', 'cms_page', 'menu'] as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }

        /*
         * A catalogue of this test's own.
         *
         * What is under test here is revisions, rollback and the filtering of
         * a retired token — none of which has an opinion about which sections
         * a theme ships. Borrowing whatever the shipped registry happens to
         * declare made these tests fail the day a theme's sections changed,
         * for reasons that had nothing to do with history.
         */
        $this->app->instance(SectionRegistry::class, new SectionRegistry([
            'intro' => [
                'label' => 'Giới thiệu',
                'variants' => ['boxed' => 'Trong khung'],
                'settings' => ['background', 'alignment'],
            ],
        ]));
    }

    private function editor(): User
    {
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['pages.update', 'media.view']]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function site(): SiteContentService
    {
        // Scoped, so the request-level cache has to be dropped between checks.
        app(SiteContentService::class)->forget();

        return app(SiteContentService::class);
    }

    private function sections(): SiteSectionService
    {
        app(SiteSectionService::class)->forget();

        return app(SiteSectionService::class);
    }

    private function saveBlock(string $value): void
    {
        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'dev.sandbox.title',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'vi',
            'value' => $value,
        ])->assertOk();
    }

    public function test_history_lists_what_was_there_before_each_editing_session(): void
    {
        $this->actingAs($this->editor());

        $this->saveBlock('Bản đầu');
        // Coalescing means one checkpoint per session, so history grows by moving on
        // in time rather than by typing more.
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản ba');

        $response = $this->getJson('/vi/admin/site-blocks/revisions?key=dev.sandbox.title')->assertOk();

        $revisions = $response->json('data.revisions');

        // Newest first, and each entry holds the value that was replaced.
        $this->assertCount(2, $revisions);
        $this->assertSame('Bản hai', $revisions[0]['preview']);
        $this->assertSame('Bản đầu', $revisions[1]['preview']);
    }

    public function test_a_region_that_was_only_ever_saved_once_has_no_history(): void
    {
        // Correct, not a missing revision: there was nothing before the first save.
        $this->actingAs($this->editor());
        $this->saveBlock('Chỉ một bản');

        $this->getJson('/vi/admin/site-blocks/revisions?key=dev.sandbox.title')
            ->assertOk()
            ->assertJsonPath('data.revisions', []);
    }

    public function test_history_of_a_region_nobody_edited_is_empty_rather_than_missing(): void
    {
        $this->actingAs($this->editor());

        $this->getJson('/vi/admin/site-blocks/revisions?key=dev.sandbox.never-touched')
            ->assertOk()
            ->assertJsonPath('data.revisions', []);
    }

    public function test_rolling_back_puts_the_old_value_in_place(): void
    {
        $this->actingAs($this->editor());

        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');

        $revision = SiteBlockRevision::query()->firstOrFail();

        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.value', 'Bản đầu');

        $this->assertSame('Bản đầu', $this->site()->value('dev.sandbox.title', 'vi'));
    }

    public function test_rolling_back_is_itself_undoable(): void
    {
        /*
         * The property that makes an immediate rollback acceptable. The snapshot is
         * forced rather than coalesced: within the ten-minute window a coalesced
         * snapshot would be skipped, and the value being replaced would be gone for
         * good — the one operation in the editor with no way back.
         */
        $this->actingAs($this->editor());

        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');

        $this->assertDatabaseCount('site_block_revisions', 1);

        $revision = SiteBlockRevision::query()->firstOrFail();
        // Deliberately inside the coalescing window of the save just made.
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertOk();

        $this->assertDatabaseCount('site_block_revisions', 2);

        // And the value the rollback replaced is reachable from the new checkpoint.
        $latest = SiteBlockRevision::query()->latest('id')->firstOrFail();
        $this->assertSame('Bản hai', $latest->content['vi']);

        $this->postJson("/vi/admin/site-blocks/revisions/{$latest->id}/restore")->assertOk();
        $this->assertSame('Bản hai', $this->site()->value('dev.sandbox.title', 'vi'));
    }

    public function test_rolling_back_touches_only_the_locale_being_viewed(): void
    {
        /*
         * The spatie trap: `getTranslations()` filters empty strings and
         * `setTranslations()` cannot remove a locale, so both sides read and write the
         * raw column. A rollback that went through the trait would quietly take the
         * other languages with it.
         */
        Language::query()->updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);

        $this->actingAs($this->editor());

        $this->saveBlock('Tiếng Việt một');
        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'dev.sandbox.title',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'en',
            'value' => 'English text',
        ])->assertOk();

        $this->travel(11)->minutes();
        $this->saveBlock('Tiếng Việt hai');

        $revision = SiteBlockRevision::query()->latest('id')->firstOrFail();
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertOk();

        $stored = SiteBlock::query()->firstWhere('key', 'dev.sandbox.title')->rawTranslations();

        $this->assertSame('Tiếng Việt một', $stored['vi']);
        $this->assertSame('English text', $stored['en'], 'rollback đã kéo theo locale khác');
    }

    public function test_a_revision_without_the_viewed_locale_cannot_be_restored(): void
    {
        // Rather than writing an empty string, which would read as "hidden on
        // purpose" — a completely different instruction.
        Language::query()->updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true]);

        $this->actingAs($this->editor());

        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'dev.sandbox.title',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'en',
            'value' => 'English one',
        ])->assertOk();
        $this->travel(11)->minutes();
        $this->patchJson('/vi/admin/site-blocks', [
            'key' => 'dev.sandbox.title',
            'type' => SiteBlock::TYPE_TEXT,
            'content_locale' => 'en',
            'value' => 'English two',
        ])->assertOk();

        $revision = SiteBlockRevision::query()->latest('id')->firstOrFail();

        // The snapshot holds `en` only, and the request is being made under `vi`.
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")
            ->assertStatus(422)
            ->assertJsonValidationErrors('revision');
    }

    public function test_the_target_comes_from_the_revision_and_never_from_the_request(): void
    {
        /*
         * Accepting a key alongside the revision id would let a crafted call write
         * one region's snapshot onto another, and nothing in the audit trail would
         * show it. The endpoint takes no key at all.
         */
        $this->actingAs($this->editor());

        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');

        $other = SiteBlock::query()->create(['key' => 'dev.sandbox.intro', 'type' => 'text', 'content' => ['vi' => 'Không được đổi']]);
        $revision = SiteBlockRevision::query()->firstOrFail();

        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore", [
            'key' => 'dev.sandbox.intro',
        ])->assertOk();

        $this->assertSame('Không được đổi', $other->fresh()->rawTranslations()['vi']);
        $this->assertSame('Bản đầu', $this->site()->value('dev.sandbox.title', 'vi'));
    }

    public function test_section_history_reads_in_the_registrys_own_words(): void
    {
        // A list of `bg-dark, pad-lg` is a list nobody can read back.
        $this->actingAs($this->editor());

        $this->patchJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY, 'section' => 'intro',
            'variant' => 'boxed', 'settings' => ['background' => 'dark'], 'is_visible' => true,
        ])->assertOk();
        $this->travel(11)->minutes();
        $this->patchJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY, 'section' => 'intro',
            'variant' => null, 'settings' => ['background' => 'muted'], 'is_visible' => false,
        ])->assertOk();

        $revisions = $this->getJson('/vi/admin/site-sections/revisions?list_key='.self::LIST_KEY.'&section=intro')
            ->assertOk()
            ->json('data.revisions');

        $this->assertCount(1, $revisions);
        $this->assertStringContainsString('Trong khung', $revisions[0]['preview']);
        $this->assertStringContainsString('Tối', $revisions[0]['preview']);
    }

    public function test_rolling_a_section_back_restores_variant_settings_and_visibility(): void
    {
        $this->actingAs($this->editor());

        $this->patchJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY, 'section' => 'intro',
            'variant' => 'boxed', 'settings' => ['background' => 'dark'], 'is_visible' => true,
        ])->assertOk();
        $this->travel(11)->minutes();
        $this->patchJson('/vi/admin/site-sections', [
            'list_key' => self::LIST_KEY, 'section' => 'intro',
            'variant' => null, 'settings' => [], 'is_visible' => false,
        ])->assertOk();

        $revision = SiteSectionRevision::query()->latest('id')->firstOrFail();

        $this->postJson("/vi/admin/site-sections/revisions/{$revision->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.variant', 'boxed')
            ->assertJsonPath('data.hidden', false);

        $this->assertSame(['background' => 'dark'], $this->sections()->settings(self::LIST_KEY, 'intro'));
    }

    public function test_a_snapshot_naming_a_retired_token_comes_back_filtered(): void
    {
        /*
         * An old snapshot can name a token since removed from the registry. Restoring
         * it verbatim would put a value in the row that no read path will ever honour
         * — the registry would stop being the source of truth it claims to be.
         */
        $stored = SiteSection::query()->create([
            'key' => self::LIST_KEY.'.intro',
            'variant' => null,
            'settings' => [],
            'is_visible' => true,
        ]);
        $revision = SiteSectionRevision::query()->create([
            'site_section_id' => $stored->id,
            'created_by' => null,
            'variant' => 'retired-variant',
            'settings' => ['background' => 'retired-token', 'alignment' => 'center'],
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($this->editor());
        $this->postJson("/vi/admin/site-sections/revisions/{$revision->id}/restore")->assertOk();

        $this->assertNull($stored->fresh()->variant);
        $this->assertSame(['alignment' => 'center'], $stored->fresh()->settings);
    }

    public function test_guests_and_customers_can_neither_read_history_nor_roll_back(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor);
        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');
        $revision = SiteBlockRevision::query()->firstOrFail();

        // History names the admins who edited the site; that is not public.
        $this->app['auth']->forgetGuards();
        $this->getJson('/vi/admin/site-blocks/revisions?key=dev.sandbox.title')->assertUnauthorized();
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertUnauthorized();

        // A customer is a user with no role; they authenticate on the same guard.
        $this->actingAs(User::factory()->create(['role_id' => null]));
        $this->getJson('/vi/admin/site-blocks/revisions?key=dev.sandbox.title')->assertForbidden();
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertForbidden();

        $this->assertSame('Bản hai', $this->site()->value('dev.sandbox.title', 'vi'));
    }

    public function test_an_admin_without_pages_update_cannot_roll_back(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor);
        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');
        $revision = SiteBlockRevision::query()->firstOrFail();

        $role = Role::query()->create(['name' => 'Viewer', 'permissions' => ['pages.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));

        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertForbidden();
    }

    public function test_a_rollback_is_written_to_the_activity_log(): void
    {
        // Rolling back changes what the public site says, so it belongs in the trail
        // next to the edits it undoes.
        $this->actingAs($this->editor());
        $this->saveBlock('Bản đầu');
        $this->travel(11)->minutes();
        $this->saveBlock('Bản hai');

        $revision = SiteBlockRevision::query()->firstOrFail();
        $this->postJson("/vi/admin/site-blocks/revisions/{$revision->id}/restore")->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'restored',
            'subject_type' => SiteBlock::class,
        ]);
    }
}
