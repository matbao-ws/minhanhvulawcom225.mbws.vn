<?php

namespace Tests\Feature;

use App\Models\AiBulkJob;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FeatureSetting;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\ShippingPartner;
use App\Models\User;
use App\Models\Voucher;
use App\Support\AdminTours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The guided tours (App\Support\AdminTours, driver.js). A tour points at elements by their
 * data-tour attribute; when a view changes, these tests fail instead of a tour quietly losing
 * its steps. Every page of the sidebar must have a tour.
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
        // AI pages talk to the MatBao gateway: answer "not configured" instead of calling out.
        config(['ai_gateway.url' => 'https://gateway.test', 'ai_gateway.token' => 'mbp_tour']);
        Http::fake(['*' => Http::response(['ok' => false, 'reason' => 'not_configured'], 503)]);

        $role = Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        $this->root = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_every_page_with_a_tour_carries_its_required_targets_and_the_tour(): void
    {
        $parameters = $this->fixtures();

        foreach (AdminTours::routes() as $route => $tour) {
            $url = route($route, ['locale' => 'vi'] + ($parameters[$route] ?? []));
            $response = $this->actingAs($this->root)->get($url);
            $this->assertSame(200, $response->status(), "{$route} ({$url}) answered {$response->status()}");
            $html = $response->getContent();

            $config = $this->tourConfig($html);
            $this->assertNotNull($config, "{$route} renders its tour");
            $this->assertSame($tour, $config['id'], $route);
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

    public function test_every_page_of_the_sidebar_has_a_tour(): void
    {
        preg_match_all("/route\\('(admin\\.[a-z0-9_.-]+)'/", File::get(resource_path('views/admin/layouts/sidebar.blade.php')), $m);

        $missing = array_values(array_diff(array_unique($m[1]), array_keys(AdminTours::routes())));

        $this->assertSame([], $missing, 'Sidebar pages without a guided tour: add them to app/Support/AdminTours/tours.php');
    }

    public function test_a_page_without_a_tour_loads_nothing_of_it(): void
    {
        Route::middleware(['web', 'auth'])->get('/vi/admin/_tour-probe', fn () => view('admin.layouts.app'))->name('tests.tour-probe');

        $html = $this->actingAs($this->root)->get('/vi/admin/_tour-probe')->assertOk()->getContent();

        $this->assertNull($this->tourConfig($html));
        $this->assertStringNotContainsString('data-admin-tour-start', $html);
        $this->assertStringNotContainsString('driver.js.iife.js', $html);
    }

    public function test_every_target_exists_in_some_view_even_the_optional_ones(): void
    {
        $views = collect(File::allFiles(resource_path('views/admin')))->map(fn ($file) => $file->getContents())->implode("\n");
        $present = $this->targetsIn($views);
        // Anchors built in a loop, e.g. data-tour="ai-bulk.kind-{{ $kind }}".
        preg_match_all('/data-tour="([^"{]+)\{\{/', $views, $m);
        $prefixes = $m[1];

        foreach (AdminTours::tours() as $tour) {
            foreach (AdminTours::steps($tour) as $step) {
                $target = $step['target'];
                if ($target === null || in_array($target, $present, true)) {
                    continue;
                }
                $built = array_filter($prefixes, fn ($prefix) => str_starts_with($target, $prefix));
                $this->assertNotEmpty($built, "tour {$tour}: no view has data-tour=\"{$target}\"");
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
                        $key = "tours.{$step['key']}.{$part}";
                        $this->assertTrue(trans()->hasForLocale($key, $locale), "{$locale}: {$key}");
                        $this->assertDoesNotMatchRegularExpression('/[<>]/', trans($key, [], $locale), "{$locale}: {$key} is plain text");
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
        $this->assertNull(AdminTours::forRoute('admin.posts.show', 1), 'a redirect has no tour');
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

    /** @return array<string, array<string, mixed>> route => parameters, for the tour routes that take some */
    private function fixtures(): array
    {
        $product = Product::query()->create(['name' => ['vi' => 'Ống nhựa'], 'slug' => 'ong-nhua', 'sku' => 'ON-1', 'price' => 1000, 'is_active' => true]);
        $group = ProductOptionGroup::query()->create(['product_id' => $product->id, 'name' => ['vi' => 'Màu sắc'], 'code' => 'mau-sac']);
        $value = ProductOptionValue::query()->create(['product_option_group_id' => $group->id, 'label' => ['vi' => 'Đỏ'], 'code' => 'do', 'is_active' => true]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'ON-1-DO', 'price' => 1000, 'stock_quantity' => 1, 'is_active' => true]);
        $variant->optionValues()->attach($value->id);

        $order = Order::query()->create([
            'order_number' => 'ORD-TOUR-1', 'customer_name' => 'Khách', 'customer_email' => 'khach@example.com', 'customer_phone' => '0900000000',
            'shipping_address' => '1 Lê Lợi', 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending',
            'subtotal' => 1000, 'discount' => 0, 'grand_total' => 1000,
        ]);
        $menu = Menu::query()->create(['key' => 'tour', 'name' => 'Menu thử', 'is_active' => true]);
        $item = MenuItem::query()->create(['menu_id' => $menu->id, 'label' => ['vi' => 'Giới thiệu'], 'type' => MenuItem::TYPE_URL, 'url' => '/vi/lien-he', 'is_active' => true, 'sort_order' => 1]);
        $staffRole = Role::query()->create(['name' => 'Tour staff', 'is_system' => false, 'is_superadmin' => false, 'permissions' => ['products.view']]);
        $staff = User::factory()->create(['role_id' => $staffRole->id, 'email' => 'tour-staff@example.com']);

        return [
            'admin.products.edit' => ['product' => $product],
            'admin.products.show' => ['product' => $product],
            'admin.products.options.edit' => ['product' => $product],
            'admin.products.variants.create' => ['product' => $product],
            'admin.products.variants.edit' => ['product' => $product, 'variant' => $variant],
            'admin.categories.edit' => ['category' => Category::query()->create(['name' => ['vi' => 'Ống nước'], 'slug' => 'ong-nuoc', 'is_active' => true])],
            'admin.brands.edit' => ['brand' => Brand::query()->create(['name' => ['vi' => 'Bình Minh'], 'slug' => 'binh-minh', 'is_active' => true])],
            'admin.promotions.edit' => ['promotion' => Promotion::query()->create(['name' => ['vi' => 'Giảm hè'], 'kind' => 'automatic', 'applies_to' => 'all_products', 'discount_type' => 'percentage', 'value' => 10, 'is_active' => true])],
            'admin.vouchers.edit' => ['voucher' => Voucher::query()->create(['code' => 'TOUR10', 'name' => ['vi' => 'Giảm 10%'], 'type' => 'percentage', 'value' => 10, 'is_active' => true])],
            'admin.orders.show' => ['order' => $order],
            'admin.customers.show' => ['email' => $order->customer_email],
            'admin.payment-methods.edit' => ['payment_method' => PaymentMethod::query()->create(['method_code' => 'PM_TOUR0001', 'name' => 'Ví điện tử', 'type' => 'custom', 'status' => 'inactive', 'settings' => ['description' => 'Chuyển qua ví']])],
            'admin.shipping-partners.edit' => ['shipping_partner' => ShippingPartner::query()->create(['partner_code' => 'DTGHTOUR0001', 'name' => 'Tự giao nội thành', 'type' => 'custom', 'status' => 'inactive', 'settings' => ['fee' => 30000]])],
            'admin.posts.edit' => ['post' => Post::query()->create(['title' => ['vi' => 'Bài thử'], 'slug' => 'bai-thu', 'content' => ['vi' => '<p>x</p>']])],
            'admin.post-categories.edit' => ['post_category' => PostCategory::query()->create(['name' => ['vi' => 'Tin tức'], 'slug' => 'tin-tuc', 'is_active' => true, 'sort_order' => 1])],
            'admin.pages.edit' => ['page' => Page::query()->create(['title' => ['vi' => 'Bảo hành'], 'slug' => 'bao-hanh', 'published_html' => ['vi' => '<p>x</p>'], 'is_active' => true, 'published_at' => now()])],
            'admin.banners.edit' => ['banner' => Banner::query()->create(['title' => 'Sale', 'image_path' => 'banners/sale.jpg', 'position' => 'home_main', 'sort_order' => 1, 'is_active' => true])],
            'admin.menus.edit' => ['menu' => $menu],
            'admin.menus.items.index' => ['menu' => $menu],
            'admin.menus.items.create' => ['menu' => $menu],
            'admin.menus.items.edit' => ['menu' => $menu, 'item' => $item],
            'admin.users.edit' => ['user' => $staff],
            'admin.roles.edit' => ['role' => $staffRole],
            'admin.ai.bulk.show' => ['job' => AiBulkJob::query()->create(['gateway_job_id' => 990001, 'type' => 'bulk.meta', 'locale' => 'vi', 'status' => 'completed', 'items_total' => 0, 'credits_estimated' => 0, 'items' => [], 'imported_at' => now()])],
        ];
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
