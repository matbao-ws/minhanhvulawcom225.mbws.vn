<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A theme's home page (`Route::get('/', …)->name('home')` in routes/theme.php) owns the site's
 * root. The core only sends / to the admin when no theme claims it — never over a theme's home,
 * which is what visitors of a themed website type in.
 */
class ThemeHomeRouteTest extends TestCase
{
    use RefreshDatabase;

    private string $theme;

    protected function setUp(): void
    {
        // Routes are read when the application boots: the theme must exist before it does.
        $this->theme = sys_get_temp_dir().'/theme-home-'.uniqid();
        mkdir($this->theme, 0777, true);
        file_put_contents($this->theme.'/routes.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => 'theme home')->name('home');\nRoute::get('gioi-thieu', fn () => 'about')->name('about');\n");
        putenv('DEMO_THEME_PATH='.$this->theme);
        $_ENV['DEMO_THEME_PATH'] = $_SERVER['DEMO_THEME_PATH'] = $this->theme;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('DEMO_THEME_PATH');
        unset($_ENV['DEMO_THEME_PATH'], $_SERVER['DEMO_THEME_PATH']);
        File::deleteDirectory($this->theme);

        parent::tearDown();
    }

    public function test_the_themes_home_page_answers_the_root(): void
    {
        $this->get('/')->assertOk()->assertSee('theme home');
        $this->get('/gioi-thieu')->assertOk()->assertSee('about');
        $this->get('/en')->assertOk()->assertSee('theme home');
        $this->get('/vi')->assertRedirect('/');
    }
}
