<?php

namespace Tests\Feature;

use App\Support\ErrorReference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * A server error page shows a reference code, never the exception: messages carry SQL, database
 * names, hosts and paths. The log line carries the same code with the detail.
 */
class ErrorPageReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErrorReference::forget();
        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException(
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_spam' (Connection: mysql, Host: 10.0.0.5, Database: shop_secret)"
        ));
    }

    public function test_production_shows_only_a_reference_and_the_log_has_the_detail(): void
    {
        config(['app.debug' => false]);
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $page = $this->get('/__boom')->assertStatus(500);

        $this->assertSame(1, preg_match('/ERR-\d{14}-[A-Z0-9]{4}/', $page->getContent(), $shown), 'a reference on the page');
        $page->assertSee('Mã lỗi')
            ->assertDontSee('SQLSTATE')->assertDontSee('shop_secret')->assertDontSee('10.0.0.5')->assertDontSee('RuntimeException');

        // The same code in the log, next to the detail: that is how support finds it.
        $entry = collect($logged)->first(fn ($e) => str_contains($e->message, 'SQLSTATE'));
        $this->assertNotNull($entry, 'the detail is logged');
        $this->assertSame($shown[0], $entry->context['error_ref']);
    }

    public function test_a_developer_machine_still_sees_the_detail(): void
    {
        config(['app.debug' => true]);

        // Laravel's own debug page takes over with debug on; the detail is visible either way.
        $this->get('/__boom')->assertStatus(500)->assertSee('SQLSTATE');
    }
}
