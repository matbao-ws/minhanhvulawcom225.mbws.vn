<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploaded media is addressed as "/storage/<path>". Normally the web server
 * answers that from the public/storage symlink and never reaches PHP, but an
 * FTP deploy onto shared hosting frequently cannot create that link. The
 * application must still serve the file rather than fail.
 *
 * The failure this pins down: the private "local" disk used to be served at the
 * same "/storage" prefix. It won the route, demanded a signed URL, and turned
 * every image on a freshly deployed site into a 403.
 */
class PublicMediaServingTest extends TestCase
{
    public function test_public_media_is_served_when_the_storage_symlink_is_missing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/shoe.jpg', 'binary-image-bytes');

        $response = $this->get('/storage/products/shoe.jpg');

        $response->assertOk();
        $this->assertSame('binary-image-bytes', $response->streamedContent());
    }

    public function test_serving_media_does_not_require_a_signature(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/hero.png', 'hero');

        // No signature, no session, no authentication: exactly what an <img>
        // tag sends. A 403 here is the production bug this test guards.
        $this->get('/storage/banners/hero.png')->assertOk();
    }

    public function test_missing_media_reports_not_found_rather_than_forbidden(): void
    {
        Storage::fake('public');

        $this->get('/storage/products/absent.jpg')->assertNotFound();
    }

    public function test_the_private_disk_is_not_reachable_over_http(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secret.txt', 'private-contents');

        $this->assertFalse(
            Route::has('storage.local'),
            'The private disk must not be published; it squats the public media URL.',
        );

        $response = $this->get('/storage/secret.txt');

        $response->assertNotFound();
        $response->assertDontSee('private-contents');
    }

    public function test_the_upload_route_that_ships_with_serving_rejects_unsigned_writes(): void
    {
        Storage::fake('public');

        // Publishing the disk also registers a PUT endpoint. It demands a
        // signed URL regardless of the disk being public, and no caller in this
        // application ever mints one.
        // Laravel reports the rejection as 404 in production and 403 elsewhere;
        // what matters is that nothing was written.
        $this->call('PUT', '/storage/products/planted.php', content: '<?php echo 1;')
            ->assertForbidden();

        Storage::disk('public')->assertMissing('products/planted.php');
    }

    public function test_served_media_keeps_its_sandbox_policy(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('general/drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $response = $this->get('/storage/general/drawing.svg');

        // Serving through PHP bypasses the deny rules in
        // storage/app/public/.htaccess, so the sandbox that neutralises script
        // inside an uploaded SVG must survive the site-wide security headers.
        $response->assertOk();
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }
}
