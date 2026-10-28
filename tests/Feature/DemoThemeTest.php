<?php

namespace Tests\Feature;

use App\Support\DemoTheme;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** A theme plugged into the core checkout for trying changes: off unless asked, never in git or production. */
class DemoThemeTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('framework/testing/demo-theme-'.uniqid());
        File::ensureDirectoryExists($this->folder.'/views/client/pages');
        File::put($this->folder.'/views/client/pages/demo-probe.blade.php', 'demo theme view');
        File::put($this->folder.'/routes.php', '<?php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        parent::tearDown();
    }

    public function test_it_is_off_unless_the_variable_names_an_existing_folder(): void
    {
        config(['app.demo_theme' => null]);
        $this->assertNull(DemoTheme::path());
        $this->assertNull(DemoTheme::views());

        config(['app.demo_theme' => 'no-such-folder']);
        $this->assertNull(DemoTheme::path());
    }

    public function test_it_supplies_its_views_and_routes_when_on(): void
    {
        config(['app.demo_theme' => $this->folder]);

        $this->assertSame($this->folder.'/views', DemoTheme::views());
        $this->assertSame($this->folder.'/routes.php', DemoTheme::routes());

        // Its views are found before the website's own resources/views/client.
        View::prependLocation((string) DemoTheme::views());
        $this->assertSame('demo theme view', view('client.pages.demo-probe')->render());
    }

    public function test_it_is_never_active_in_production(): void
    {
        config(['app.demo_theme' => $this->folder]);
        $this->app['env'] = 'production';

        $this->assertNull(DemoTheme::path());
        $this->assertNull(DemoTheme::views());
        $this->assertNotSame($this->folder.'/routes.php', DemoTheme::routes());
    }

    /** Out of git, so `team.py new` (a clone) and core releases never carry it. deploy.py: tests/python. */
    public function test_it_stays_out_of_git(): void
    {
        $ignored = file(base_path('.gitignore'), FILE_IGNORE_NEW_LINES);
        $this->assertContains('/demo-theme', $ignored);
        $this->assertContains('/public/demo-theme-assets', $ignored);
    }
}
