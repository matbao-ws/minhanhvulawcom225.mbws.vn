<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

/**
 * Two profiles: clean() for storefront site blocks and imports (their look is classes and
 * design tokens, never inline styles) and cleanRichText() for what the admin editor writes.
 */
class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_clean_keeps_no_inline_styles_frames_or_figures(): void
    {
        $html = $this->sanitizer->clean('<p style="text-align:center">A</p><figure><img src="/a.jpg" alt="a"><figcaption>c</figcaption></figure>'
            .'<iframe src="https://www.youtube.com/embed/x"></iframe>');

        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('<figure', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_rich_text_keeps_editor_formatting(): void
    {
        $html = $this->sanitizer->cleanRichText(
            '<p style="text-align: center;">A</p>'
            .'<p><img src="https://example.com/a.jpg" alt="a" style="width: 300px; float: left;"></p>'
            .'<figure><img src="/a.jpg" alt="b"><figcaption>Chú thích</figcaption></figure>'
            .'<iframe width="400" height="300" src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>'
            .'<iframe src="https://player.vimeo.com/video/1"></iframe>'
            .'<a href="https://example.com" target="_blank" rel="nofollow">x</a>'
        );

        $this->assertStringContainsString('<p style="text-align:center;">A</p>', $html);
        $this->assertStringContainsString('style="width:300px;float:left;"', $html);
        $this->assertStringContainsString('<figcaption>Chú thích</figcaption></figure>', $html);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc"', $html);
        $this->assertStringContainsString('src="https://player.vimeo.com/video/1"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('nofollow', $html);
    }

    public function test_rich_text_still_drops_anything_unsafe(): void
    {
        $html = $this->sanitizer->cleanRichText(
            '<p style="position: fixed; top: 0; background-image: url(javascript:alert(1)); color: red" onclick="x()">A</p>'
            .'<iframe src="https://evil.example/embed"></iframe><script>alert(1)</script>'
            .'<a href="javascript:alert(1)">x</a><img src="data:image/png;base64,AAAA" alt="">'
        );

        $this->assertStringNotContainsString('position', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringContainsString('color:#FF0000;', $html);
    }
}
