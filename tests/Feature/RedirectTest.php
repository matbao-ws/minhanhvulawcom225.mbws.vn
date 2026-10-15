<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use App\Services\LocalizedSlugService;
use App\Services\Seo\RedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Old URLs answer with their redirect once nothing else answers them (a site moved from
 * WordPress keeps its rankings), and what still answers 404 is logged for admin/redirects.
 */
class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        // What a theme's routes/theme.php declares for its post page.
        Route::middleware(['web', 'setLocale'])->get('{locale}/bai-viet/{slug}', fn () => 'theme page')->name('client.posts.show');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_an_old_url_redirects_permanently_and_counts_the_hit(): void
    {
        $redirect = Redirect::query()->create(['source_path' => '/ten-bai-cu', 'target_url' => '/bai-viet/ten-bai-moi']);

        $this->get('/ten-bai-cu/')->assertStatus(301)->assertRedirect('/bai-viet/ten-bai-moi');
        // Capitals, a trailing slash and percent-encoding do not matter.
        $this->get('/Ten-Bai-Cu')->assertStatus(301);

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
        $this->assertSame(0, NotFoundLog::query()->count());
    }

    public function test_query_parameters_ride_along_unless_the_rule_names_its_own(): void
    {
        Redirect::query()->create(['source_path' => '/cu', 'target_url' => '/moi']);
        Redirect::query()->create(['source_path' => '/cu?p=7', 'target_url' => '/bai-7']);

        $this->get('/cu?utm_source=fb')->assertRedirect('/moi?utm_source=fb');
        $this->get('/cu?p=7')->assertRedirect('/bai-7');
    }

    public function test_a_vietnamese_slug_matches_however_it_is_encoded(): void
    {
        Redirect::query()->create(['source_path' => RedirectService::normalize('/bài-viết-cũ/'), 'target_url' => '/moi']);

        $this->get('/b%C3%A0i-vi%E1%BA%BFt-c%C5%A9/')->assertRedirect('/moi');
    }

    public function test_a_redirect_to_a_record_follows_its_slug_when_it_changes(): void
    {
        $post = $this->makePost('cach-chon-giay');
        app(RedirectService::class)->remember('https://old-site.vn/cach-chon-giay-chay-bo/', $post);

        $this->get('/cach-chon-giay-chay-bo/')->assertRedirect('/bai-viet/cach-chon-giay');

        $post->update(['slug' => 'chon-giay-chay']);
        app(LocalizedSlugService::class)->sync($post, ['vi' => 'chon-giay-chay']);

        $this->get('/cach-chon-giay-chay-bo/')->assertRedirect('/bai-viet/chon-giay-chay');
    }

    public function test_gone_answers_410_and_a_switched_off_rule_answers_404(): void
    {
        Redirect::query()->create(['source_path' => '/da-xoa', 'status_code' => 410]);
        Redirect::query()->create(['source_path' => '/tat', 'target_url' => '/moi', 'is_active' => false]);

        $this->get('/da-xoa')->assertStatus(410);
        $this->get('/tat')->assertNotFound();
        $this->assertTrue(NotFoundLog::query()->where('path', '/tat')->exists());
    }

    public function test_a_page_that_exists_is_never_redirected(): void
    {
        Page::query()->create(['title' => ['vi' => 'Đổi trả'], 'slug' => 'doi-tra', 'published_html' => ['vi' => '<p>x</p>'], 'is_active' => true, 'published_at' => now()->subDay()]);
        Redirect::query()->create(['source_path' => '/chinh-sach/doi-tra', 'target_url' => '/moi']);

        $this->get('/chinh-sach/doi-tra')->assertOk();
    }

    public function test_a_redirect_to_itself_is_ignored(): void
    {
        Redirect::query()->create(['source_path' => '/vong-lap', 'target_url' => '/vong-lap/']);

        $this->get('/vong-lap')->assertNotFound();
    }

    public function test_404s_are_logged_with_hits_and_referer_but_not_admin_api_or_probes(): void
    {
        $this->get('/khong-co', ['referer' => 'https://google.com/'])->assertNotFound();
        $this->get('/khong-co/')->assertNotFound();

        $log = NotFoundLog::query()->where('path', '/khong-co')->first();
        $this->assertSame(2, $log->hits);
        $this->assertSame('https://google.com/', $log->referer);

        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/.env')->assertNotFound();
        $this->getJson('/api/khong-co')->assertNotFound();
        $this->assertSame(1, NotFoundLog::query()->count());
    }

    public function test_remember_skips_unchanged_urls_and_never_overwrites_an_admin_redirect(): void
    {
        $post = $this->makePost('giu-nguyen');
        $redirects = app(RedirectService::class);

        $this->assertNull($redirects->remember('https://old.vn/bai-viet/giu-nguyen/', $post));

        Redirect::query()->create(['source_path' => '/tay', 'target_url' => '/do-admin-chon', 'origin' => Redirect::ORIGIN_MANUAL]);
        $redirects->remember('https://old.vn/tay/', $post);
        $this->assertSame('/do-admin-chon', Redirect::query()->where('source_path', '/tay')->value('target_url'));
    }

    public function test_links_to_old_addresses_are_rewritten_to_the_new_ones(): void
    {
        $post = $this->makePost('bai-moi');
        $redirects = app(RedirectService::class);
        $redirects->remember('https://old.vn/bai-cu/', $post);

        $html = $redirects->rewriteLinks(
            '<a href="https://www.old.vn/bai-cu/#muc-2">a</a><a href="/bai-cu/">b</a><a href="https://khac.vn/bai-cu/">c</a><a href="https://old.vn/khong-biet/">d</a>',
            ['old.vn'],
        );

        $this->assertStringContainsString('href="/bai-viet/bai-moi#muc-2"', $html);
        $this->assertStringContainsString('href="/bai-viet/bai-moi">b', $html);
        $this->assertStringContainsString('href="https://khac.vn/bai-cu/"', $html);
        $this->assertStringContainsString('href="https://old.vn/khong-biet/"', $html);
    }

    public function test_admin_lists_creates_and_edits_redirects(): void
    {
        $admin = $this->admin(['redirects.view', 'redirects.create', 'redirects.update', 'redirects.delete']);
        $log = NotFoundLog::query()->create(['path' => '/link-hong', 'hits' => 3, 'last_seen_at' => now()]);

        $this->actingAs($admin)->get('/vi/admin/redirects?tab=not-found')->assertOk()->assertSee('/link-hong');

        $this->actingAs($admin)->post('/vi/admin/redirects', [
            'source' => 'https://old.vn/Link-Hong/', 'target' => '/bai-viet/moi', 'status_code' => 301, 'is_active' => 1, 'not_found_id' => $log->id,
        ])->assertRedirect('/vi/admin/redirects');

        $redirect = Redirect::query()->where('source_path', '/link-hong')->firstOrFail();
        $this->assertSame(Redirect::ORIGIN_NOT_FOUND, $redirect->origin);
        $this->assertSame(0, NotFoundLog::query()->count(), 'the 404 it fixes leaves the log');

        $this->actingAs($admin)->get('/vi/admin/redirects')->assertOk()->assertSee('/link-hong')->assertSee('/bai-viet/moi')
            // A one-line subtitle; what the page is for sits behind the "?" beside the title.
            ->assertSee(__('redirects.subtitle'))
            ->assertSee('admin-page-help__toggle', false)
            ->assertSee(__('redirects.guide.code_301'))
            ->assertSee(__('redirects.guide.when_404'));

        $this->actingAs($admin)->put('/vi/admin/redirects/'.$redirect->id, [
            'source' => '/link-hong', 'target' => '', 'status_code' => 410, 'is_active' => 1,
        ])->assertRedirect('/vi/admin/redirects');
        $this->assertSame(410, $redirect->fresh()->status_code);
        $this->assertNull($redirect->fresh()->target_url);
    }

    public function test_a_hand_edited_target_replaces_the_record_the_import_pointed_at(): void
    {
        $admin = $this->admin(['redirects.view', 'redirects.update']);
        $redirect = app(RedirectService::class)->remember('https://old.vn/bai-cu/', $this->makePost('bai-moi'));

        $this->actingAs($admin)->put('/vi/admin/redirects/'.$redirect->id, [
            'source' => '/bai-cu', 'target' => '/trang-khac', 'status_code' => 301, 'is_active' => 1,
        ]);

        $redirect->refresh();
        $this->assertNull($redirect->redirectable_type);
        $this->assertSame(Redirect::ORIGIN_MANUAL, $redirect->origin);
        $this->get('/bai-cu')->assertRedirect('/trang-khac');
    }

    public function test_the_form_refuses_loops_duplicates_the_home_page_and_odd_targets(): void
    {
        $admin = $this->admin(['redirects.view', 'redirects.create']);
        Redirect::query()->create(['source_path' => '/da-co', 'target_url' => '/x']);

        $post = fn (array $data) => $this->actingAs($admin)->from('/vi/admin/redirects')->post('/vi/admin/redirects', $data + ['status_code' => 301]);

        $post(['source' => '/a', 'target' => '/a/'])->assertSessionHasErrors('target');
        $post(['source' => '/da-co/', 'target' => '/y'])->assertSessionHasErrors('source');
        $post(['source' => '/', 'target' => '/y'])->assertSessionHasErrors('source');
        $post(['source' => '/b', 'target' => 'javascript:alert(1)'])->assertSessionHasErrors('target');
        $post(['source' => '/b', 'target' => '//evil.example'])->assertSessionHasErrors('target');
        $this->assertSame(1, Redirect::query()->count());
    }

    public function test_csv_import_and_export(): void
    {
        $admin = $this->admin(['redirects.view', 'redirects.create']);
        Redirect::query()->create(['source_path' => '/tay', 'target_url' => '/giu', 'origin' => Redirect::ORIGIN_MANUAL]);
        $csv = "\xEF\xBB\xBFsource,target,status\n/cu-1/,/moi-1,301\nhttps://old.vn/cu-2/,https://example.com/x,302\n/da-xoa/,,410\n/tay/,/de-len,301\n/hong,javascript:x,301\n";

        $this->actingAs($admin)->post('/vi/admin/redirects/import', [
            'file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv),
        ])->assertRedirect('/vi/admin/redirects')->assertSessionHas('success');

        $this->assertSame('/moi-1', Redirect::query()->where('source_path', '/cu-1')->value('target_url'));
        $this->assertSame(302, Redirect::query()->where('source_path', '/cu-2')->value('status_code'));
        $this->assertSame(410, Redirect::query()->where('source_path', '/da-xoa')->value('status_code'));
        $this->assertSame('/giu', Redirect::query()->where('source_path', '/tay')->value('target_url'));
        $this->assertFalse(Redirect::query()->where('source_path', '/hong')->exists());

        $export = $this->actingAs($admin)->get('/vi/admin/redirects/export');
        $export->assertOk();
        $this->assertStringContainsString('/cu-1,/moi-1,301', $export->streamedContent());
    }

    public function test_each_action_needs_its_permission(): void
    {
        $viewer = $this->admin(['redirects.view']);
        $redirect = Redirect::query()->create(['source_path' => '/cu', 'target_url' => '/moi']);

        $this->actingAs($viewer)->get('/vi/admin/redirects')->assertOk()->assertDontSee('data-redirect-open="create"', false);
        $this->actingAs($viewer)->post('/vi/admin/redirects', ['source' => '/a', 'target' => '/b', 'status_code' => 301])->assertForbidden();
        $this->actingAs($viewer)->delete('/vi/admin/redirects/'.$redirect->id)->assertForbidden();
        $this->actingAs($this->admin(['redirects.view', 'redirects.update']))
            ->patch('/vi/admin/redirects/bulk', ['ids' => [$redirect->id], 'action' => 'delete'])->assertForbidden();
        $this->actingAs($this->admin(['posts.view']))->get('/vi/admin/redirects')->assertForbidden();
    }

    public function test_old_404_rows_are_pruned(): void
    {
        NotFoundLog::query()->create(['path' => '/cu', 'last_seen_at' => now()->subDays(120)]);
        NotFoundLog::query()->create(['path' => '/moi', 'last_seen_at' => now()->subDay()]);

        $this->artisan('redirects:prune-404')->assertSuccessful();

        $this->assertSame(['/moi'], NotFoundLog::query()->pluck('path')->all());
    }

    private function makePost(string $slug): Post
    {
        $post = Post::query()->create(['title' => ['vi' => $slug], 'slug' => $slug, 'content' => ['vi' => '<p>x</p>'], 'is_active' => true]);
        app(LocalizedSlugService::class)->sync($post, ['vi' => $slug]);

        return $post;
    }

    /** @param list<string> $permissions */
    private function admin(array $permissions): User
    {
        return User::factory()->create(['role_id' => Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions])->id]);
    }
}
