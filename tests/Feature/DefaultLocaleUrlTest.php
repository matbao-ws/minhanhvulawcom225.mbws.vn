<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Page;
use App\Services\LanguageRegistry;
use App\Services\Seo\SitemapService;
use App\Support\LocaleUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The default language's storefront has no /{locale} prefix (/chinh-sach/x), the others keep it
 * (/en/chinh-sach/x), /vi/... moved there for good, and the admin keeps its prefix.
 * MULTILINGUAL_HIDE_DEFAULT_LOCALE=false brings the prefixed URLs back.
 */
class DefaultLocaleUrlTest extends TestCase
{
    use RefreshDatabase;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        FeatureSetting::query()->updateOrCreate(['feature_code' => 'cms_page'], ['is_enabled' => true]);
        $this->page = Page::query()->create([
            'title' => ['vi' => 'Đổi trả', 'en' => 'Returns'],
            'slug' => 'doi-tra',
            'published_html' => ['vi' => '<p>Nội dung tiếng Việt</p>', 'en' => '<p>English content</p>'],
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
        $this->assertSame('vi', app(LanguageRegistry::class)->defaultLocale(), 'precondition: vi is the default language');
    }

    public function test_the_default_language_has_no_prefix_and_the_others_keep_it(): void
    {
        $vi = $this->page->canonicalSlug('vi');
        $en = $this->page->canonicalSlug('en');

        $this->get('/chinh-sach/'.$vi)->assertOk()->assertSee('Nội dung tiếng Việt', false);
        $this->get('/en/chinh-sach/'.$en)->assertOk()->assertSee('English content', false);

        $this->assertSame(url('/chinh-sach/'.$vi), route('client.pages.show', ['locale' => 'vi', 'slug' => $vi]));
        $this->assertSame(url('/en/chinh-sach/'.$en), route('client.pages.show', ['locale' => 'en', 'slug' => $en]));
    }

    public function test_old_prefixed_addresses_move_permanently_and_keep_their_query(): void
    {
        $slug = $this->page->canonicalSlug('vi');

        $this->get('/vi/chinh-sach/'.$slug.'?utm_source=zalo')->assertStatus(301)->assertRedirect(url('/chinh-sach/'.$slug.'?utm_source=zalo'));
        // The path used before the rename: one hop to the page.
        $this->get('/pages/'.$slug)->assertStatus(301)->assertRedirect(url('/chinh-sach/'.$slug));
    }

    public function test_the_admin_keeps_its_prefix(): void
    {
        $response = $this->get('/vi/admin');

        $this->assertNotSame(301, $response->getStatusCode());
        $this->assertStringContainsString('/vi/admin', (string) $response->headers->get('Location'));
        $this->assertSame(url('/vi/admin'), route('admin.dashboard', ['locale' => 'vi']));
    }

    public function test_search_tags_and_the_sitemap_use_the_new_addresses(): void
    {
        $vi = url('/chinh-sach/'.$this->page->canonicalSlug('vi'));
        $en = url('/en/chinh-sach/'.$this->page->canonicalSlug('en'));

        $this->get($vi)->assertOk()
            ->assertSee('<link rel="canonical" href="'.$vi.'">', false)
            ->assertSee('<link rel="alternate" hreflang="vi" href="'.$vi.'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$en.'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$vi.'">', false);

        SitemapService::flush();
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.$vi.'</loc>', false)
            ->assertSee('<loc>'.$en.'</loc>', false)
            ->assertDontSee('/vi/chinh-sach/', false);
    }

    public function test_the_root_still_opens_the_admin_without_a_theme_home(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_switching_it_off_brings_the_prefixed_addresses_back(): void
    {
        config(['multilingual.hide_default_locale' => false]);
        $slug = $this->page->canonicalSlug('vi');

        $this->get('/vi/chinh-sach/'.$slug)->assertOk()->assertSee('Nội dung tiếng Việt', false);
        $this->get('/chinh-sach/'.$slug)->assertNotFound();
        $this->get('/')->assertRedirect('/admin');
        $this->assertSame(url('/vi/chinh-sach/'.$slug), route('client.pages.show', ['locale' => 'vi', 'slug' => $slug]));
        $this->assertSame(url('/vi/chinh-sach/x'), LocaleUrl::switchTo('vi', 'en/chinh-sach/x'));
    }

    public function test_a_kernel_booted_before_any_request_still_starts(): void
    {
        // Queue workers and the checkout race (CheckoutConcurrencyTest) boot the HTTP kernel with no
        // request yet: the URL formatter must not force the URL generator into existence then.
        $code = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php";'
            .' $app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap(); echo "booted";';

        $result = Process::path(base_path())->run([PHP_BINARY, '-r', $code]);

        $this->assertSame('booted', trim($result->output()), $result->errorOutput());
    }

    public function test_the_language_switch_of_a_page_follows_the_same_rule(): void
    {
        $this->assertSame(url('/en/chinh-sach/x'), LocaleUrl::switchTo('en', 'chinh-sach/x'));
        $this->assertSame(url('/chinh-sach/x'), LocaleUrl::switchTo('vi', 'en/chinh-sach/x'));
        // Admin pages always carry the prefix.
        $this->assertSame(url('/en/admin/orders'), LocaleUrl::switchTo('en', 'vi/admin/orders'));
        $this->assertSame(url('/vi/admin/orders'), LocaleUrl::switchTo('vi', 'en/admin/orders'));
    }
}
