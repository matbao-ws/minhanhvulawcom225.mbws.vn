<?php

namespace Tests\Unit;

use App\Support\PageHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Page HTML renders on the storefront and inside admin screens (preview, the
 * edit form), on the same origin as the admin session: anything executable
 * that survives here runs as whichever admin opens the page.
 */
class PageHtmlSanitizerTest extends TestCase
{
    private PageHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new PageHtmlSanitizer;
    }

    /**
     * Raw-text elements hold markup as unescaped text. Unwrapping one used to
     * hand that text back to the parent, where it serialised as live markup.
     *
     * @return array<string, array{string}>
     */
    public static function rawTextPayloads(): array
    {
        return [
            'style' => ['<p>hi</p><style><img src=x onerror=alert(1)></style>'],
            'noscript' => ['<p>hi</p><noscript><img src=x onerror=alert(1)></noscript>'],
            'template' => ['<p>hi</p><template><img src=x onerror=alert(1)></template>'],
            'title' => ['<p>hi</p><title><img src=x onerror=alert(1)></title>'],
            'xmp' => ['<p>hi</p><xmp><img src=x onerror=alert(1)></xmp>'],
            'noembed' => ['<p>hi</p><noembed><img src=x onerror=alert(1)></noembed>'],
            'noframes' => ['<p>hi</p><noframes><img src=x onerror=alert(1)></noframes>'],
            'nested in svg' => ['<svg><style><img src=x onerror=alert(1)></style></svg>'],
            'nested twice' => ['<div><noscript><style><img src=x onerror=alert(1)></style></noscript></div>'],
        ];
    }

    #[DataProvider('rawTextPayloads')]
    public function test_raw_text_elements_cannot_smuggle_markup(string $payload): void
    {
        $clean = $this->sanitizer->clean($payload);

        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<img', $clean);
    }

    public function test_head_only_elements_are_dropped(): void
    {
        $clean = $this->sanitizer->clean('<p>a</p><base href="https://evil.test/"><link rel="stylesheet" href="https://evil.test/x.css"><meta http-equiv="refresh" content="0;url=https://evil.test">');

        $this->assertSame('<p>a</p>', $clean);
    }

    public function test_cleaning_is_idempotent(): void
    {
        $html = '<section class="hero"><h1>Tiêu đề</h1><p>Nội dung <a href="/x" target="_blank">link</a></p><img src="/storage/a.jpg" alt="a"><unknown><b>giữ chữ</b></unknown></section>';

        $once = $this->sanitizer->clean($html);

        $this->assertSame($once, $this->sanitizer->clean($once));
        $this->assertStringContainsString('<b>giữ chữ</b>', $once);
    }
}
