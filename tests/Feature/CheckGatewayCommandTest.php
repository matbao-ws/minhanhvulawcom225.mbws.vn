<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** `gateway:check` names what keeps a website from the gateway, without printing the token. */
class CheckGatewayCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_gateway.url' => 'https://gw.test', 'ai_gateway.token' => 'mbp_secret_token']);
    }

    public function test_a_working_connection(): void
    {
        Http::fake(['gw.test/v1/project' => Http::response(['ok' => true, 'project' => ['tool_domain' => 'shop.vn']])]);

        $this->artisan('gateway:check')
            ->expectsOutputToContain('Kết nối gateway OK')
            ->doesntExpectOutputToContain('mbp_secret_token')
            ->assertExitCode(0);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer mbp_secret_token'));
    }

    public function test_a_rejected_token(): void
    {
        Http::fake(['gw.test/v1/project' => Http::response(['ok' => false, 'reason' => 'unauthorized'], 401)]);

        $this->artisan('gateway:check')->expectsOutputToContain('Gateway từ chối token')->assertExitCode(1);
    }

    public function test_an_unreachable_gateway(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));

        $this->artisan('gateway:check')->expectsOutputToContain('Không kết nối được gateway: cURL error 28')->assertExitCode(1);
    }

    public function test_missing_configuration(): void
    {
        config(['ai_gateway.token' => null]);

        $this->artisan('gateway:check')->expectsOutputToContain('Chưa cấu hình MATBAO_AI_URL')->assertExitCode(1);
    }
}
