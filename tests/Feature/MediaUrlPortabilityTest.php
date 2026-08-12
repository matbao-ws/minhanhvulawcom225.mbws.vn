<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\ProjectSetting;
use App\Models\User;
use App\Services\SiteBranding;
use App\Support\HtmlSanitizer;
use App\Support\MediaUrl;
use App\Support\PageHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Media that lives on this site must never be pinned to the host that happened
 * to be configured when it was uploaded: it is stored relative and resolved
 * against APP_URL on read. External assets must be preserved verbatim.
 */
class MediaUrlPortabilityTest extends TestCase
{
    use RefreshDatabase;

    private function useAppUrl(string $url): void
    {
        config(['app.url' => $url]);
        url()->forceRootUrl($url);
        url()->forceScheme(parse_url($url, PHP_URL_SCHEME));
    }

    private function makeProduct(?string $imageUrl): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'name' => ['vi' => "San pham {$n}", 'en' => "Product {$n}"],
            'slug' => "product-{$n}",
            'image_url' => $imageUrl,
        ]);
    }

    private function makePost(): Post
    {
        static $n = 0;
        $n++;

        return Post::create([
            'title' => ['vi' => "Bai viet {$n}", 'en' => "Post {$n}"],
            'slug' => "post-{$n}",
            'content' => ['vi' => '<p>Noi dung</p>', 'en' => '<p>Content</p>'],
        ]);
    }

    public function test_our_own_media_is_stored_relative_and_resolved_against_app_url(): void
    {
        $this->useAppUrl('https://first.test');

        $product = $this->makeProduct('https://first.test/storage/products/a.jpg');

        $this->assertSame('/storage/products/a.jpg', DB::table('products')->where('id', $product->id)->value('image_url'));
        $this->assertSame('https://first.test/storage/products/a.jpg', $product->fresh()->image_url);

        $this->useAppUrl('https://second.test');

        $this->assertSame('https://second.test/storage/products/a.jpg', $product->fresh()->image_url);
    }

    public function test_relative_seeded_paths_follow_app_url(): void
    {
        $this->useAppUrl('https://seeded.test');

        $product = $this->makeProduct('/images/products/glasswool.png');

        $this->assertSame('https://seeded.test/images/products/glasswool.png', $product->fresh()->image_url);
    }

    public function test_app_url_subdirectory_is_preserved(): void
    {
        $this->useAppUrl('https://host.test/shop');

        $product = $this->makeProduct('/storage/products/a.jpg');

        $this->assertSame('https://host.test/shop/storage/products/a.jpg', $product->fresh()->image_url);
    }

    public function test_external_media_is_left_untouched(): void
    {
        $this->useAppUrl('https://host.test');

        foreach ([
            'https://res.cloudinary.com/demo/image/upload/v1/a.jpg',
            'https://images.unsplash.com/photo-1?w=100',
            // An external CDN that happens to serve a /storage/ path must not be
            // mistaken for one of ours.
            'https://cdn.example.com/storage/a.jpg',
            'data:image/png;base64,AAAA',
        ] as $external) {
            $product = $this->makeProduct($external);

            $this->assertSame($external, DB::table('products')->where('id', $product->id)->value('image_url'));
            $this->assertSame($external, $product->fresh()->image_url);
        }
    }

    public function test_a_stale_host_is_healed_when_the_file_is_one_of_ours(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posts/legacy.jpg', 'x');

        $this->useAppUrl('https://current.test');

        // Simulates a row written before this behaviour existed.
        $post = $this->makePost();
        DB::table('posts')->where('id', $post->id)->update(['image_url' => 'http://localhost:8000/storage/posts/legacy.jpg']);

        $this->assertSame('https://current.test/storage/posts/legacy.jpg', $post->fresh()->image_url);
    }

    public function test_a_stale_host_is_not_rewritten_when_the_file_is_unknown(): void
    {
        Storage::fake('public');

        $this->useAppUrl('https://current.test');

        $post = $this->makePost();
        DB::table('posts')->where('id', $post->id)->update(['image_url' => 'http://someone-else.test/storage/nope.jpg']);

        $this->assertSame('http://someone-else.test/storage/nope.jpg', $post->fresh()->image_url);
    }

    public function test_avatar_and_branding_follow_app_url(): void
    {
        $this->useAppUrl('https://brand.test');

        $user = User::factory()->create(['avatar_url' => 'https://brand.test/storage/avatars/a.png', 'role_id' => null]);
        ProjectSetting::updateOrCreate(['setting_key' => 'logo_url'], ['setting_value' => '/storage/settings/logo.png']);

        $this->assertSame('/storage/avatars/a.png', DB::table('users')->where('id', $user->id)->value('avatar_url'));
        $this->assertSame('https://brand.test/storage/avatars/a.png', $user->fresh()->avatar_url);
        $this->assertSame('https://brand.test/storage/settings/logo.png', app(SiteBranding::class)->current()['logo_url']);
    }

    public function test_public_disk_path_extraction(): void
    {
        $this->useAppUrl('https://host.test');

        $this->assertSame('avatars/a.png', MediaUrl::publicDiskPath('https://host.test/storage/avatars/a.png'));
        $this->assertSame('avatars/a.png', MediaUrl::publicDiskPath('/storage/avatars/a.png'));
        $this->assertNull(MediaUrl::publicDiskPath('https://res.cloudinary.com/demo/a.png'));
        $this->assertNull(MediaUrl::publicDiskPath('/images/a.png'));
        $this->assertNull(MediaUrl::publicDiskPath('/storage/../../.env'));
        $this->assertNull(MediaUrl::publicDiskPath(null));
    }

    public function test_rich_text_image_sources_are_stored_relative(): void
    {
        $this->useAppUrl('https://editor.test');

        $cleaned = app(HtmlSanitizer::class)->clean(
            '<p><img src="https://editor.test/storage/posts/a.jpg" alt="a">'
            .'<img src="https://res.cloudinary.com/demo/b.jpg" alt="b"></p>'
        );

        $this->assertStringContainsString('src="/storage/posts/a.jpg"', $cleaned);
        $this->assertStringContainsString('src="https://res.cloudinary.com/demo/b.jpg"', $cleaned);
    }

    public function test_page_builder_html_sources_are_stored_relative(): void
    {
        $this->useAppUrl('https://editor.test');

        $cleaned = app(PageHtmlSanitizer::class)->clean(
            '<div><img src="https://editor.test/storage/posts/a.jpg" '
            .'srcset="https://editor.test/storage/posts/a.jpg 1x, https://cdn.example.com/b.jpg 2x"></div>'
        );

        $this->assertStringContainsString('src="/storage/posts/a.jpg"', $cleaned);
        $this->assertStringContainsString('srcset="/storage/posts/a.jpg 1x, https://cdn.example.com/b.jpg 2x"', $cleaned);
    }

    public function test_html_source_rewriting_is_reversible_and_idempotent(): void
    {
        $this->useAppUrl('https://host.test');

        $stored = '<img src="/storage/a.jpg" srcset="/storage/a.jpg 1x, https://cdn.example.com/b.jpg 2x" poster="/storage/p.jpg">';

        $absolute = MediaUrl::absolutizeHtmlSources($stored);

        $this->assertStringContainsString('src="https://host.test/storage/a.jpg"', $absolute);
        $this->assertStringContainsString('poster="https://host.test/storage/p.jpg"', $absolute);
        $this->assertStringContainsString('https://cdn.example.com/b.jpg 2x', $absolute);

        $this->assertSame($stored, MediaUrl::relativizeHtmlSources($absolute));
        $this->assertSame($stored, MediaUrl::relativizeHtmlSources($stored));
        $this->assertSame($absolute, MediaUrl::absolutizeHtmlSources($absolute));
    }

    public function test_serialised_output_carries_the_resolved_url_not_the_stored_path(): void
    {
        $this->useAppUrl('https://json.test');

        $product = $this->makeProduct('/storage/products/a.jpg');
        $user = User::factory()->create(['avatar_url' => '/storage/avatars/a.png', 'role_id' => null]);

        // toArray()/toJson() must agree with property access — a decoupled
        // frontend consumes the serialised form.
        $this->assertSame('https://json.test/storage/products/a.jpg', $product->fresh()->toArray()['image_url']);
        $this->assertSame('https://json.test/storage/avatars/a.png', $user->fresh()->toArray()['avatar_url']);
    }

    public function test_gallery_images_are_portable_too(): void
    {
        $this->useAppUrl('https://gallery.test');

        $product = $this->makeProduct(null);
        $image = $product->images()->create(['image_url' => 'https://gallery.test/storage/products/g.jpg']);

        $this->assertSame('/storage/products/g.jpg', DB::table('product_images')->where('id', $image->id)->value('image_url'));
        $this->assertSame('https://gallery.test/storage/products/g.jpg', $image->fresh()->image_url);
    }

    public function test_relative_sources_in_html_are_left_byte_for_byte_alone(): void
    {
        $this->useAppUrl('https://host.test');

        // "images/a.jpg" is page-relative; rewriting it to "/images/a.jpg"
        // would change what it points at, so it must survive untouched even
        // when the same document contains an absolute URL we do rewrite.
        $html = '<img src="images/a.jpg"><img src="https://host.test/storage/b.jpg">';

        $this->assertSame(
            '<img src="images/a.jpg"><img src="/storage/b.jpg">',
            MediaUrl::relativizeHtmlSources($html),
        );
    }

    public function test_email_logo_is_not_pinned_to_a_stale_host(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', 'x');
        ProjectSetting::updateOrCreate(['setting_key' => 'logo_url'], ['setting_value' => '/storage/settings/logo.png']);

        $this->useAppUrl('https://mail.test');

        $logo = app(SiteBranding::class)->current()['logo_url'];

        $this->assertSame('https://mail.test/storage/settings/logo.png', $logo);
        $this->assertStringNotContainsString('localhost', $logo);
    }

    public function test_no_absolute_local_media_urls_remain_in_the_database(): void
    {
        $this->useAppUrl('https://host.test');

        $this->makeProduct('https://host.test/storage/products/a.jpg');
        User::factory()->create(['avatar_url' => 'https://host.test/storage/avatars/a.png']);

        foreach ([['products', 'image_url'], ['users', 'avatar_url']] as [$table, $column]) {
            $this->assertSame(
                0,
                DB::table($table)->where($column, 'like', 'http%://%/storage/%')->count(),
                "{$table}.{$column} still holds an absolute URL for local media.",
            );
        }
    }
}
