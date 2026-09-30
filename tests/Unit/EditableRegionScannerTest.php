<?php

namespace Tests\Unit;

use App\Support\EditableRegionScanner;
use PHPUnit\Framework\TestCase;

/**
 * The scanner decides what an editor is allowed to be offered.
 *
 * Most of these tests are about what it must *refuse*. A false positive here
 * is not a cosmetic bug: `--fix` acts on it, so a wrong candidate becomes a
 * second place to edit a product name, or a region that swallows a `@foreach`.
 * The false negatives matter less — a region nobody wrapped is the situation
 * this class was written to improve, not to guarantee away.
 */
class EditableRegionScannerTest extends TestCase
{
    private function keys(string $source, string $view = 'home'): array
    {
        return array_column((new EditableRegionScanner)->scan($source, $view), 'key');
    }

    private function previews(string $source, string $view = 'home'): array
    {
        return array_column((new EditableRegionScanner)->scan($source, $view), 'preview');
    }

    public function test_it_finds_authored_copy_in_a_plain_block(): void
    {
        $findings = (new EditableRegionScanner)->scan(
            '<section class="hero"><h1>Xin chào</h1><p>Mô tả ngắn.</p></section>',
            'home',
        );

        $this->assertSame(['home.hero.h1-1', 'home.hero.p-1'], array_column($findings, 'key'));
        $this->assertSame(['h1', 'p'], array_column($findings, 'tag'));
    }

    public function test_it_never_offers_content_printed_from_a_variable(): void
    {
        // The whole safety argument. A Blade echo means a controller supplied
        // the value, so the value has an admin screen already, and marking it
        // would create the second editing surface this mechanism exists to
        // prevent.
        $this->assertSame([], $this->keys('<div><p>{{ $product->name }}</p></div>'));
        $this->assertSame([], $this->keys('<div><p>{!! $description !!}</p></div>'));
        $this->assertSame([], $this->keys('<div><p>Giá: {{ $price }} đ</p></div>'));
    }

    public function test_it_never_offers_content_whose_shape_depends_on_a_directive(): void
    {
        $this->assertSame([], $this->keys('<ul><li>@if($a)Có@else Không @endif</li></ul>'));
        $this->assertSame([], $this->keys('<p>@lang("messages.hello")</p>'));
    }

    public function test_an_email_address_is_copy_and_not_a_directive(): void
    {
        // `@` alone cannot disqualify content, or every contact block in a
        // theme silently drops out of the audit.
        $this->assertSame(['home.main.p-1'], $this->keys('<p>lienhe@shop.vn</p>'));
    }

    public function test_it_skips_regions_that_are_already_marked(): void
    {
        $source = <<<'BLADE'
        <div class="wrap">
            <x-client::editable key="a.b.c" tag="p">Đã bọc</x-client::editable>
            <p data-block-key="a.b.d">Bọc tay</p>
        </div>
        BLADE;

        $this->assertSame([], $this->keys($source));
    }

    public function test_it_honours_contenteditable_false_on_an_ancestor(): void
    {
        // Dynamic blocks rendered onto a CMS page carry this on their wrapper
        // precisely so the editor stays out; the audit has to agree.
        $this->assertSame([], $this->keys('<div contenteditable="false"><p>Menu</p></div>'));
    }

    public function test_the_body_of_a_cms_page_belongs_to_the_page_not_to_the_template(): void
    {
        // Everything under the editable root is saved as one document into
        // pages.published_html by the admin bar. Marking a region inside it
        // would store the same words again in site_blocks, and the next
        // whole-document save would overwrite what the region rendered.
        $source = '<main data-client-editable-root><h1>Tiêu đề trang</h1><p>Thân trang.</p></main>';

        $this->assertSame([], $this->keys($source));
    }

    public function test_it_honours_the_opt_out_marker(): void
    {
        $source = '<div><p '.EditableRegionScanner::IGNORE_ATTRIBUTE.'>Trang chủ / Tin</p><p>Thật</p></div>';

        $this->assertSame(['Thật'], $this->previews($source));
    }

    public function test_a_candidate_containing_structure_is_not_a_region(): void
    {
        // A cell holding a layout is not a sentence. Wrapping it would make the
        // editor responsible for markup it cannot rebuild.
        $this->assertSame([], $this->keys('<table><tr><td><div class="x">Hộp</div></td></tr></table>'));
    }

    public function test_inline_formatting_stays_part_of_its_paragraph(): void
    {
        $findings = (new EditableRegionScanner)->scan('<div><p>Đổi trả trong <strong>7 ngày</strong></p></div>', 'home');

        $this->assertCount(1, $findings);
        $this->assertSame('p', $findings[0]['tag']);
        $this->assertStringContainsString('<strong>7 ngày</strong>', $findings[0]['inner']);
    }

    public function test_only_the_outermost_candidate_is_offered(): void
    {
        // Closing tags arrive innermost first, so this is the case the
        // post-scan containment filter exists for.
        $findings = (new EditableRegionScanner)->scan('<blockquote>Lời dẫn <em>nhấn</em></blockquote>', 'home');

        $this->assertCount(1, $findings);
        $this->assertSame('blockquote', $findings[0]['tag']);
    }

    public function test_it_ignores_markup_inside_comments_scripts_and_php_blocks(): void
    {
        $source = <<<'BLADE'
        {{-- <p>Trong comment</p> --}}
        @php $x = '<p>Trong php</p>'; @endphp
        <script>document.write('<p>Trong script</p>');</script>
        <style>/* <p>Trong style</p> */</style>
        BLADE;

        $this->assertSame([], $this->keys($source));
    }

    public function test_an_unbalanced_conditional_tag_does_not_derail_the_walk(): void
    {
        // Themes open a tag in one branch and close it in another all the time.
        // The walk has to survive it and keep finding the regions after it.
        $source = <<<'BLADE'
        <div class="a">
            @if($wide)</div><div class="b">@endif
            <p>Vẫn tìm thấy</p>
        </div>
        BLADE;

        $this->assertSame(['Vẫn tìm thấy'], $this->previews($source));
    }

    public function test_an_image_from_the_theme_is_offered_but_one_from_a_record_is_not(): void
    {
        $theme = "<div class=\"hero\"><img src=\"{{ asset('client-assets/img/a.jpg') }}\" alt=\"A\"></div>";
        $record = '<div class="hero"><img src="{{ $product->image_url }}" alt="B"></div>';

        $this->assertSame(['home.hero.img-1'], $this->keys($theme));
        $this->assertSame([], $this->keys($record));
    }

    public function test_whitespace_and_entities_alone_are_not_a_region(): void
    {
        $this->assertSame([], $this->keys('<div><p>   </p><p>&nbsp;</p><li>&mdash;</li></div>'));
    }

    public function test_the_area_segment_is_borrowed_from_the_nearest_named_ancestor(): void
    {
        $this->assertSame(['home.uu-dai.h2-1'], $this->keys('<section id="uu-dai"><h2>A</h2></section>'));
        $this->assertSame(['home.promo-band.h2-1'], $this->keys('<section class="promo-band x"><h2>A</h2></section>'));
        $this->assertSame(['home.section.h2-1'], $this->keys('<section><h2>A</h2></section>'));
        $this->assertSame(['home.main.h2-1'], $this->keys('<h2>A</h2>'));
    }

    public function test_generated_keys_satisfy_the_rule_the_save_endpoint_enforces(): void
    {
        // Copied from SiteBlockUpdateRequest. A key this scanner invents has to
        // be one the server would accept, or `--fix` writes regions that can be
        // displayed and never saved.
        $pattern = '/^[a-z0-9]+(?:[_-][a-z0-9]+)*(?:\.[a-z0-9]+(?:[_-][a-z0-9]+)*)+$/';

        $source = <<<'BLADE'
        <section class="Khối__Ưu Đãi--2024">
            <h2>Một</h2>
            <p>Hai</p>
            <p>Ba</p>
        </section>
        BLADE;

        $keys = $this->keys($source, 'catalog.danh_muc');

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression($pattern, $key, "key {$key} sẽ bị SiteBlockUpdateRequest từ chối");
            $this->assertLessThanOrEqual(191, strlen($key));
        }
    }

    public function test_ordinals_are_dense_and_scoped_to_one_area_and_tag(): void
    {
        // A gap in the sequence reads like a region someone deleted, so keys
        // are numbered only after the nested candidates have been discarded.
        $source = <<<'BLADE'
        <section id="a"><p>Một</p><blockquote><p>Hai</p></blockquote><p>Ba</p></section>
        <section id="b"><p>Bốn</p></section>
        BLADE;

        $this->assertSame(
            ['home.a.p-1', 'home.a.p-2', 'home.a.p-3', 'home.b.p-1'],
            $this->keys($source),
        );
    }

    public function test_the_reported_attributes_keep_blade_echoes_intact(): void
    {
        // The tokenizer runs on a masked copy; a fix built from those masked
        // attributes would write `class="\x01\x01"` into the theme.
        $findings = (new EditableRegionScanner)->scan(
            '<div><p class="{{ $theme }} lead" data-x="1">Nội dung</p></div>',
            'home',
        );

        $this->assertCount(1, $findings);
        $this->assertSame(' class="{{ $theme }} lead" data-x="1"', $findings[0]['attrs']);
    }

    public function test_offsets_delimit_exactly_the_element_that_was_found(): void
    {
        // `--fix` splices on these two numbers and nothing else.
        $source = '<div class="a">   <p class="b">Nội dung</p>   </div>';
        $findings = (new EditableRegionScanner)->scan($source, 'home');

        $this->assertCount(1, $findings);
        $this->assertSame(
            '<p class="b">Nội dung</p>',
            substr($source, $findings[0]['start'], $findings[0]['end'] - $findings[0]['start']),
        );
    }
}
