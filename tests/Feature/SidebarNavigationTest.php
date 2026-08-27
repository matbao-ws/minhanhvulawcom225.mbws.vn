<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\Role;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (config('features.codes', []) as $featureCode) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $featureCode],
                ['is_enabled' => false],
            );
        }
    }

    public function test_sidebar_hides_links_the_admin_cannot_open(): void
    {
        $role = Role::query()->create([
            'name' => 'Settings only',
            'permissions' => ['settings.view', 'settings.update', 'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete', 'payments.view', 'payments.create', 'payments.update', 'payments.delete'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($admin)->get('/vi/admin');
        $links = $this->adminLinks($response->getContent());

        $response->assertOk();
        $this->assertNotContains('/vi/admin/products', $links);
        $this->assertNotContains('/vi/admin/posts', $links);
        $this->assertNotContains('/vi/admin/banners', $links);
        $this->assertNotContains('/vi/admin/users', $links);
        $this->assertNotContains('/vi/admin/media', $links);
        $this->assertContains('/vi/admin/settings', $links);
        // setUp disables every feature, so these two are exactly what this test
        // is named for: links the admin can no longer open. General and
        // notification settings carry no feature switch and stay.
        $this->assertNotContains('/vi/admin/shipping-partners', $links);
        $this->assertNotContains('/vi/admin/payment-methods', $links);
        $this->assertContains('/vi/admin/notification-settings', $links);
        $response->assertSee('data-sidebar-settings-menu', false);
        $response->assertSeeText('Cấu hình chung');
        $response->assertDontSeeText('Cấu hình tính năng');
    }

    public function test_every_rendered_superadmin_sidebar_link_opens_without_dashboard_feature_redirect(): void
    {
        $role = Role::query()->create([
            'name' => 'Superadmin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $superadmin = User::factory()->create(['role_id' => $role->id]);

        $sidebar = $this->actingAs($superadmin)->get('/vi/admin')->assertOk();
        $links = $this->adminLinks($sidebar->getContent());

        $this->assertContains('/vi/admin/products', $links);
        $this->assertContains('/vi/admin/posts', $links);
        $this->assertContains('/vi/admin/banners', $links);
        $this->assertContains('/vi/admin/users', $links);
        $this->assertContains('/vi/admin/media', $links);
        $this->assertContains('/vi/admin/settings', $links);
        $this->assertContains('/vi/admin/shipping-partners', $links);
        $this->assertContains('/vi/admin/payment-methods', $links);
        $this->assertContains('/vi/admin/notification-settings', $links);
        $this->assertContains('/vi/admin/languages', $links);
        $this->assertContains('/vi/admin/features', $links);
        $sidebar->assertSee('data-sidebar-settings-menu', false);

        foreach ($links as $link) {
            $response = $this->get($link);

            $this->assertFalse(
                $response->isRedirect('/vi/admin'),
                "Sidebar link [{$link}] redirected to the dashboard.",
            );
            $this->assertLessThan(500, $response->getStatusCode(), "Sidebar link [{$link}] returned a server error.");
        }
    }

    /** @return list<string> */
    private function adminLinks(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $links = [];
        $sidebar = $document->getElementById('sidebarnav');
        if (! $sidebar) {
            return [];
        }

        foreach ($sidebar->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            $path = parse_url($href, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/vi/admin')) {
                $links[] = $path;
            }
        }

        return array_values(array_unique($links));
    }
}
