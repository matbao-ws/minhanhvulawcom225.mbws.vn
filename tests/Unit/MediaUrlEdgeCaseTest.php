<?php

namespace Tests\Unit;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Edge cases around {@see MediaUrl}. The happy paths live in
 * Tests\Feature\MediaUrlPortabilityTest; this file pins down the parsing
 * corners where a naive implementation silently corrupts a URL.
 */
class MediaUrlEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://host.test']);
        url()->forceRootUrl('https://host.test');
        url()->forceScheme('https');
    }

    public function test_disk_path_ignores_a_cache_busting_query_string(): void
    {
        $this->assertSame('avatars/a.png', MediaUrl::publicDiskPath('/storage/avatars/a.png?v=2'));
        $this->assertSame('avatars/a.png', MediaUrl::publicDiskPath('https://host.test/storage/avatars/a.png?v=2'));
    }

    public function test_disk_path_decodes_percent_encoding(): void
    {
        $this->assertSame('avatars/a b.png', MediaUrl::publicDiskPath('/storage/avatars/a%20b.png'));
    }

    public function test_disk_path_rejects_encoded_traversal(): void
    {
        $this->assertNull(MediaUrl::publicDiskPath('/storage/%2e%2e/%2e%2e/.env'));
        $this->assertNull(MediaUrl::publicDiskPath('/storage/..%2f..%2f.env'));
    }

    public function test_uppercase_scheme_is_still_treated_as_absolute(): void
    {
        $this->assertSame('/storage/a.jpg', MediaUrl::toStorable('HTTPS://host.test/storage/a.jpg'));
        $this->assertSame(
            'HTTPS://cdn.example.com/a.jpg',
            MediaUrl::toStorable('HTTPS://cdn.example.com/a.jpg'),
            'An external URL must be preserved verbatim, not mangled into a relative path.',
        );
        $this->assertSame(
            'HTTPS://cdn.example.com/a.jpg',
            MediaUrl::resolve('HTTPS://cdn.example.com/a.jpg'),
        );
    }

    public function test_srcset_preserves_a_data_uri_candidate(): void
    {
        $stored = MediaUrl::relativizeHtmlSources(
            '<img srcset="data:image/png;base64,iVBORw0KGgo= 1x, https://host.test/storage/a.jpg 2x">'
        );

        $this->assertStringContainsString('data:image/png;base64,iVBORw0KGgo= 1x', $stored);
        $this->assertStringContainsString('/storage/a.jpg 2x', $stored);
    }

    public function test_srcset_preserves_a_url_containing_a_comma(): void
    {
        $stored = MediaUrl::relativizeHtmlSources(
            '<img srcset="https://cdn.example.com/img/w_100,h_50/a.jpg 1x">'
        );

        $this->assertStringContainsString('https://cdn.example.com/img/w_100,h_50/a.jpg 1x', $stored);
    }

    public function test_query_string_survives_a_relativize_absolutize_round_trip(): void
    {
        $original = '<img src="https://host.test/storage/a.jpg?v=2">';
        $stored = MediaUrl::relativizeHtmlSources($original);

        $this->assertStringContainsString('src="/storage/a.jpg?v=2"', $stored);
        $this->assertStringContainsString('src="https://host.test/storage/a.jpg?v=2"', MediaUrl::absolutizeHtmlSources($stored));
    }

    public function test_lazy_load_attributes_are_rewritten_too(): void
    {
        $stored = MediaUrl::relativizeHtmlSources('<img data-src="https://host.test/storage/a.jpg">');

        $this->assertStringContainsString('data-src="/storage/a.jpg"', $stored);
    }

    public function test_a_same_named_attribute_suffix_is_not_rewritten(): void
    {
        $html = '<img data-fallback_src="https://host.test/storage/a.jpg">';

        $this->assertSame($html, MediaUrl::relativizeHtmlSources($html));
    }

    public function test_host_only_absolute_url_is_left_alone(): void
    {
        $this->assertSame('https://host.test', MediaUrl::toStorable('https://host.test'));
        $this->assertSame('https://host.test/', MediaUrl::toStorable('https://host.test/'));
    }

    public function test_protocol_relative_and_data_uris_are_untouched(): void
    {
        foreach (['//cdn.example.com/a.jpg', 'data:image/png;base64,AAAA'] as $value) {
            $this->assertSame($value, MediaUrl::toStorable($value));
            $this->assertSame($value, MediaUrl::resolve($value));
        }
    }

    public function test_whitespace_only_value_is_treated_as_empty(): void
    {
        $this->assertNull(MediaUrl::toStorable('   '));
        $this->assertNull(MediaUrl::resolve('   '));
        $this->assertSame('https://host.test/fallback.png', MediaUrl::resolve('   ', 'fallback.png'));
    }

    public function test_a_different_port_on_the_same_host_is_treated_as_ours(): void
    {
        // Dev environments move ports constantly; the host is what identifies us.
        $this->assertSame('/storage/a.jpg', MediaUrl::toStorable('http://host.test:8000/storage/a.jpg'));
    }

    public function test_external_host_is_not_probed_when_the_public_disk_is_remote(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('a.jpg', 'x');
        config(['filesystems.disks.public.driver' => 's3']);

        $this->assertSame(
            'https://elsewhere.test/storage/a.jpg',
            MediaUrl::toStorable('https://elsewhere.test/storage/a.jpg'),
        );
    }

    public function test_html_rewriting_leaves_non_media_attributes_alone(): void
    {
        $html = '<a href="https://host.test/storage/a.jpg">link</a>';

        $this->assertSame($html, MediaUrl::relativizeHtmlSources($html));
    }
}
