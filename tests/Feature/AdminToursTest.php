<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminTours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The guided tours (App\Support\AdminTours, driver.js). A tour points at elements by their
 * data-tour attribute; when a view changes, these tests fail instead of a tour quietly losing
 * its steps.
 */
class AdminToursTest extends TestCase
{
    use RefreshDatabase;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (config('features.codes', []) as $feature) {
            FeatureSetting::query()->updateOrCreate(['feature_code' => $feature], ['is_enabled' => true]);
        }
        $role = Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        $this->root = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_every_page_with_a_tour_carries_its_required_targets_and_the_tour(): void
    {
        $product = Product::query()->create(['name' => ['vi' => 'Ống nhựa'], 'slug' => 'ong-nhua', 'sku' => 'ON-1', 'price' => 1000, 'is_active' => true]);
        $order = Order::query()->create([
            'order_number' => 'ORD-TOUR-1', 'customer_name' => 'Khách', 'customer_email' => 'khach@example.com', 'customer_phone' => '0900000000',
            'shipping_address' => '1 Lê Lợi', 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending',
            'subtotal' => 1000, 'discount' => 0, 'grand_total' => 1000,
        ]);
        $parameters = ['admin.products.edit' => ['product' => $product], 'admin.orders.show' => ['order' => $order]];

        foreach (AdminTours::ROUTES as $route => $tour) {
            $html = $this->actingAs($this->root)->get(route($route, ['locale' => 'vi'] + ($parameters[$route] ?? [])))->assertOk()->getContent();

            $config = $this->tourConfig($html);
            $this->assertNotNull($config, "{$route} renders its tour");
            $this->assertSame($tour, $config['id']);
            $this->assertSame($this->root->id, $config['user']);
            $this->assertStringContainsString('data-admin-tour-start', $html, "{$route} shows the replay button");
            $this->assertStringContainsString('libs/driver.js/dist/driver.js.iife.js', $html);

            $present = $this->targetsIn($html);
            foreach (AdminTours::steps($tour) as $step) {
                if ($step['target'] !== null && ! $step['optional']) {
                    $this->assertContains($step['target'], $present, "{$route}: tour step {$step['key']} points at data-tour=\"{$step['target']}\", which the page no longer has");
                }
            }
        }
    }

    public function test_a_page_without_a_tour_loads_nothing_of_it(): void
    {
        $html = $this->actingAs($this->root)->get(route('admin.brands.index', ['locale' => 'vi']))->assertOk()->getContent();

        $this->assertNull($this->tourConfig($html));
        $this->assertStringNotContainsString('data-admin-tour-start', $html);
        $this->assertStringNotContainsString('driver.js.iife.js', $html);
    }

    public function test_every_target_exists_in_some_view_even_the_optional_ones(): void
    {
        $views = collect(File::allFiles(resource_path('views/admin')))->map(fn ($file) => $file->getContents())->implode("\n");
        $present = $this->targetsIn($views);

        foreach (AdminTours::tours() as $tour) {
            foreach (AdminTours::steps($tour) as $step) {
                if ($step['target'] !== null) {
                    $this->assertContains($step['target'], $present, "tour {$tour}: no view has data-tour=\"{$step['target']}\"");
                }
            }
        }
    }

    public function test_every_step_has_its_text_in_each_admin_language(): void
    {
        foreach (['vi', 'en'] as $locale) {
            foreach (['start', 'next', 'prev', 'done', 'skip', 'progress'] as $key) {
                $this->assertTrue(trans()->hasForLocale("tours.ui.{$key}", $locale), "{$locale}: tours.ui.{$key}");
            }
            foreach (AdminTours::tours() as $tour) {
                foreach (AdminTours::steps($tour) as $step) {
                    foreach (['title', 'description'] as $part) {
                        $this->assertTrue(trans()->hasForLocale("tours.{$step['key']}.{$part}", $locale), "{$locale}: tours.{$step['key']}.{$part}");
                    }
                }
            }
        }
    }

    public function test_the_text_reaches_the_browser_escaped(): void
    {
        // driver.js renders titles and descriptions as HTML.
        app('translator')->addLines(['tours.dashboard.intro.title' => '<img src=x onerror=alert(1)>'], 'vi');
        app()->setLocale('vi');

        $tour = AdminTours::forRoute('admin.dashboard', 1);

        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $tour['steps'][0]['title']);
        $this->assertNull(AdminTours::forRoute('admin.brands.index', 1));
        $this->assertNull(AdminTours::forRoute(null, 1));
    }

    public function test_a_step_whose_element_the_admin_cannot_see_is_marked_optional(): void
    {
        // An editor without orders.view gets a dashboard without order figures: those steps are optional,
        // and the page still has the tour.
        $role = Role::query()->create(['name' => 'Editor', 'permissions' => ['posts.view']]);
        $editor = User::factory()->create(['role_id' => $role->id]);

        $html = $this->actingAs($editor)->get(route('admin.dashboard', ['locale' => 'vi']))->assertOk()->getContent();

        $this->assertNotNull($this->tourConfig($html));
        $present = $this->targetsIn($html);
        foreach (AdminTours::steps('dashboard') as $step) {
            if ($step['target'] !== null && ! in_array($step['target'], $present, true)) {
                $this->assertTrue($step['optional'], "dashboard step {$step['key']} is missing for an editor but not marked optional");
            }
        }
    }

    private function tourConfig(string $html): ?array
    {
        if (! preg_match('#<script type="application/json" id="admin-tour-config">(.*?)</script>#s', $html, $m)) {
            return null;
        }

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function targetsIn(string $html): array
    {
        preg_match_all('/data-tour="([^"]+)"/', $html, $m);

        return array_values(array_unique($m[1]));
    }
}
