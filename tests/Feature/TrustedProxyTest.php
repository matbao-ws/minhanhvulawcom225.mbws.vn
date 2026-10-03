<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROXIED_HTTPS = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-Host' => 'shop.example.vn',
    ];

    public function test_behind_a_trusted_tls_proxy_urls_use_https(): void
    {
        // Staging incident: TRUSTED_PROXIES in .env was ignored, assets came out http:// and were blocked.
        config(['trustedproxy.proxies' => '*']);

        $this->withHeaders(self::PROXIED_HTTPS)->get('/vi/admin/login')
            ->assertOk()
            ->assertSee('https://shop.example.vn/admin-assets/', false)
            ->assertDontSee('http://shop.example.vn/', false);
    }

    public function test_forwarded_headers_are_ignored_until_a_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withHeaders(self::PROXIED_HTTPS)->get('/vi/admin/login')
            ->assertOk()
            ->assertDontSee('shop.example.vn', false);
    }
}
