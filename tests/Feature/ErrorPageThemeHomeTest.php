<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Services\LanguageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Themes name their home `client.home` under {locale} (routes/theme.php). The core has no such
 * route, so its own tests never built that URL: on a themed site a 404 outside the locale prefix
 * threw while rendering the error page itself.
 */
class ErrorPageThemeHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('{locale}', fn () => 'theme home')->where('locale', 'vi|en|ja')->name('client.home');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_a_404_outside_the_locale_prefix_links_to_the_theme_home(): void
    {
        $this->get('/a-definitely-missing-page-12345')
            ->assertStatus(404)
            ->assertSee('btn-back-404')
            // The default language's home has no prefix.
            ->assertSee('href="'.url('/').'"', false);
    }

    public function test_a_page_in_a_storefront_language_errs_in_that_language(): void
    {
        Language::query()->create(['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'is_active' => true, 'sort_order' => 30]);
        app(LanguageRegistry::class)->forget();

        $this->get('/ja/a-definitely-missing-page-12345')
            ->assertStatus(404)
            ->assertSee(url('/ja'), false)
            ->assertSee(trans('errors.404.heading', [], 'ja'));
    }

    /** A key added to the error pages without its Japanese text would show the raw key. */
    public function test_the_japanese_error_texts_cover_every_key(): void
    {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($lines as $key => $value) {
                $keys = is_array($value) ? [...$keys, ...$flatten($value, "{$prefix}{$key}.")] : [...$keys, "{$prefix}{$key}"];
            }

            return $keys;
        };

        $this->assertEqualsCanonicalizing(
            $flatten(require lang_path('en/errors.php')),
            $flatten(require lang_path('ja/errors.php')),
        );
    }

    public function test_the_error_views_render_without_a_request_locale(): void
    {
        $this->view('errors.500', ['exception' => null])->assertSee('HTTP 500');
        $this->view('errors.503', ['exception' => null])->assertSee('HTTP 503');
    }
}
