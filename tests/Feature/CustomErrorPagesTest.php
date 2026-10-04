<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomErrorPagesTest extends TestCase
{
    public function test_client_404_page_never_exposes_admin_dashboard(): void
    {
        $response = $this->get('/a-definitely-non-existent-public-url-12345');

        $response->assertStatus(404);
        $response->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $response->assertSee('admin-assets/images/icons/404.png');
        $response->assertSee('HTTP 404');
        $response->assertSee('btn-back-404');

        // Security check: regular client visitors must never see admin dashboard link
        $response->assertDontSee('btn-dashboard-404');
        $response->assertDontSee('Vào bảng điều khiển');
        $response->assertDontSee('system-status-pill');
    }

    public function test_admin_404_page_renders_dashboard_link(): void
    {
        $response = $this->get('/vi/admin/a-definitely-non-existent-admin-url-12345');

        $response->assertStatus(404);
        $response->assertSee('btn-dashboard-404');
        $response->assertSee('Vào bảng điều khiển');
        $response->assertDontSee('system-status-pill');
    }

    public function test_language_switch_uses_path_prefix_not_query_string(): void
    {
        $response = $this->get('/vi/admin/ai/bulkk');

        $response->assertStatus(404);
        // Switcher link must use /en/admin/ai/bulkk path prefix, NOT ?locale=en
        $response->assertSee('/en/admin/ai/bulkk');
        $response->assertDontSee('/vi/admin/ai/bulkk?locale=en');
    }

    public function test_english_admin_404_switches_back_to_vi_path(): void
    {
        $response = $this->get('/en/admin/ai/bulkk');

        $response->assertStatus(404);
        $response->assertSee('Oops! Page not found');
        $response->assertSee('/vi/admin/ai/bulkk');
    }

    public function test_500_error_view_renders_properly(): void
    {
        $view = $this->view('errors.500', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/500.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Đã xảy ra sự cố trên máy chủ');
        $view->assertSee('btn-reload-500');
        $view->assertSee('HTTP 500');
    }

    public function test_505_error_view_renders_properly(): void
    {
        $view = $this->view('errors.505', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/505.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Phiên bản giao thức không được hỗ trợ');
        $view->assertSee('btn-reload-505');
        $view->assertSee('HTTP 505');
    }

    public function test_403_error_view_renders_properly(): void
    {
        $view = $this->view('errors.403', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/403.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Bạn không có quyền truy cập khu vực này');
        $view->assertSee('HTTP 403');
    }

    public function test_419_error_view_renders_properly(): void
    {
        $view = $this->view('errors.419', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/419.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Phiên làm việc của bạn đã hết hạn');
        $view->assertSee('btn-reload-419');
        $view->assertSee('HTTP 419');
    }

    public function test_429_error_view_renders_properly(): void
    {
        $view = $this->view('errors.429', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/429.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Bạn đang thao tác quá nhanh');
        $view->assertSee('btn-reload-429');
        $view->assertSee('HTTP 429');
    }

    public function test_503_error_view_renders_properly(): void
    {
        $view = $this->view('errors.503', ['exception' => null]);

        $view->assertSee('admin-assets/images/icons/503.png');
        $view->assertSee('admin-assets/images/backgrounds/error-bg.png');
        $view->assertSee('Hệ thống đang tạm ngưng để bảo trì');
        $view->assertSee('btn-reload-503');
        $view->assertSee('HTTP 503');
    }
}
