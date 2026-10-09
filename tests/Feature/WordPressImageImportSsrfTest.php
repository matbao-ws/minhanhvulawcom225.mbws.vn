<?php

namespace Tests\Feature;

use App\Services\WordPressImageImportService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Image URLs in an uploaded WordPress export are fetched by the server, so
 * they must not reach internal hosts nor report back what answers there.
 */
class WordPressImageImportSsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_internal_addresses_hidden_inside_ipv6_are_never_fetched(): void
    {
        Http::fake();
        $importer = app(WordPressImageImportService::class);
        $importer->reset();

        foreach (['http://[::ffff:127.0.0.1]/a.jpg', 'http://[64:ff9b::7f00:1]/b.jpg', 'http://100.100.100.200/c.jpg'] as $url) {
            $this->assertSame($url, $importer->localize($url));
        }

        Http::assertNothingSent();
        $this->assertSame(3, $importer->report()['failed']);
    }

    public function test_connection_errors_do_not_describe_the_remote_end(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to 8.8.8.8 port 80: Connection refused'));
        $importer = app(WordPressImageImportService::class);
        $importer->reset();

        $importer->localize('http://8.8.8.8/a.jpg');

        $error = $importer->report()['errors'][0];
        $this->assertStringNotContainsString('refused', $error);
        $this->assertStringContainsString('không tải được ảnh', $error);
    }
}
