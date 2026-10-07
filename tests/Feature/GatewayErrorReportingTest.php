<?php

namespace Tests\Feature;

use App\Support\ErrorReference;
use App\Support\ErrorReporting\GatewayErrorReporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class GatewayErrorReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ErrorReference::forget();
        config([
            'ai_gateway.url' => 'https://gw.test',
            'ai_gateway.token' => 'mbp_project_token',
            'ai_gateway.report_errors' => true,
        ]);

        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException(
            'Duplicate entry khach.hang@example.com for key users_email, phone 0912 345 678 / +84912345678',
        ));
    }

    public function test_a_500_reaches_the_gateway_under_the_code_its_page_showed_with_personal_data_masked(): void
    {
        Http::fake(['gw.test/*' => Http::response(['ok' => true])]);

        $this->get('/__boom?email=secret@example.com')->assertStatus(500);

        $ref = ErrorReference::current();
        Http::assertSent(function (Request $request) use ($ref) {
            return $request->url() === 'https://gw.test/v1/errors'
                && $request->hasHeader('Authorization', 'Bearer mbp_project_token')
                && $request['ref'] === $ref
                && $request['status'] === 500
                && $request['method'] === 'GET'
                // No query string: it can carry personal data.
                && $request['path'] === '__boom'
                && $request['exception'] === RuntimeException::class
                && $request['message'] === 'Duplicate entry [email] for key users_email, phone [phone] / [phone]'
                && $request['file'] === 'tests/Feature/GatewayErrorReportingTest.php'
                && is_int($request['line'])
                && filled($request['core_version']);
        });
        Http::assertSentCount(1);
    }

    public function test_only_server_errors_are_sent_and_never_when_turned_off_or_not_connected(): void
    {
        Http::fake();

        $this->get('/no-such-page-here')->assertNotFound();
        Http::assertNothingSent();

        config(['ai_gateway.report_errors' => false]);
        $this->get('/__boom')->assertStatus(500);
        Http::assertNothingSent();

        config(['ai_gateway.report_errors' => true, 'ai_gateway.token' => null]);
        $this->get('/__boom')->assertStatus(500);
        Http::assertNothingSent();
    }

    public function test_an_unreachable_gateway_changes_nothing_for_the_visitor(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->get('/__boom')->assertStatus(500)->assertSee(ErrorReference::current());
    }

    public function test_a_loop_sends_at_most_the_per_minute_cap(): void
    {
        Http::fake(['gw.test/*' => Http::response(['ok' => true])]);

        for ($i = 0; $i < GatewayErrorReporter::PER_MINUTE + 5; $i++) {
            ErrorReference::forget();
            $this->get('/__boom')->assertStatus(500);
        }

        Http::assertSentCount(GatewayErrorReporter::PER_MINUTE);
    }

    public function test_masking_leaves_codes_and_dates_alone(): void
    {
        $this->assertSame(
            'order MB-20261009-ABCD at 2026-10-09 10:15:00 by [email] ([phone])',
            GatewayErrorReporter::mask('order MB-20261009-ABCD at 2026-10-09 10:15:00 by a.b@shop.vn (0987.654.321)'),
        );
    }
}
