<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Menu links typed by hand at a page that was never created.
 *
 * A `type=page` item cannot point at nothing — the column is a foreign key and
 * the request validates `exists:pages,id` — and a page deleted afterwards
 * renders as plain text rather than a broken link. A `type=url` item has
 * neither protection, so wiring a cut theme's nav with hand-typed paths before
 * the pages exist produces a menu that looks finished and 404s on click.
 *
 * Nothing reported that until this check existed.
 */
class MenuTargetAuditTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): Menu
    {
        return Menu::query()->create([
            'key' => 'primary',
            'name' => 'Menu chính',
            'is_active' => true,
        ]);
    }

    private function audit(): array
    {
        $output = new BufferedOutput;
        $status = Artisan::call('client:editable-audit', [], $output);

        return [$status, $output->fetch()];
    }

    public function test_a_hand_typed_link_to_a_missing_page_is_reported(): void
    {
        MenuItem::query()->create([
            'menu_id' => $this->menu()->id,
            'label' => ['vi' => 'Giới thiệu'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/chinh-sach/gioi-thieu',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        [$status, $output] = $this->audit();

        $this->assertSame(1, $status, "lệnh phải báo lỗi:\n".$output);
        $this->assertStringContainsString('gioi-thieu', $output);
    }

    public function test_the_same_link_is_accepted_once_the_page_exists(): void
    {
        Page::query()->create([
            'title' => ['vi' => 'Giới thiệu'],
            'slug' => 'gioi-thieu',
            'published_html' => ['vi' => '<p>Nội dung</p>'],
            'is_active' => true,
            'published_at' => now(),
        ]);

        MenuItem::query()->create([
            'menu_id' => $this->menu()->id,
            'label' => ['vi' => 'Giới thiệu'],
            'type' => MenuItem::TYPE_URL,
            'url' => '/chinh-sach/gioi-thieu',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        [$status, $output] = $this->audit();

        $this->assertSame(0, $status, $output);
    }

    public function test_links_that_are_not_page_paths_are_left_alone(): void
    {
        $menu = $this->menu();

        foreach (['https://example.com/tin-tuc', '/danh-muc/ao-thun', '/chinh-sach/a/b'] as $index => $url) {
            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'label' => ['vi' => 'Link '.$index],
                'type' => MenuItem::TYPE_URL,
                'url' => $url,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        [$status, $output] = $this->audit();

        $this->assertSame(0, $status, $output);
    }
}
