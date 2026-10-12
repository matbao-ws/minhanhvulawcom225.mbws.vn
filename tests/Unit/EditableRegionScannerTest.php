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

    public function test_static_copy_inside_a_branch_is_reported_but_never_auto_wrapped(): void
    {
        // A stored override would replace the branch along with the words, so
        // this is not something --fix may touch. It is still copy nobody can
        // edit, though, and reporting nothing is how it used to slip through.
        $findings = (new EditableRegionScanner)->scan('<ul><li>@if($a)Có@else Không @endif</li></ul>', 'home');

        $this->assertCount(1, $findings);
        $this->assertFalse($findings[0]['fixable']);
        $this->assertSame('conditional', $findings[0]['reason']);
    }

    public function test_output_directives_are_dynamic(): void
    {
        $audit = (new EditableRegionScanner)->audit('<p>@lang("messages.hello")</p><div>@include("x")</div>', 'home');

        $this->assertSame([], $audit['findings']);
        $this->assertSame(2, $audit['counts']['text']['dynamic']);
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

    public function test_contenteditable_false_is_not_an_opt_out(): void
    {
        // It keeps a block out of a CMS page's editable root; it says nothing
        // about who owns the words. The layout puts it on the whole header and
        // footer, and static copy there is exactly what a theme cut forgets.
        // Database values under it still classify as dynamic on their own.
        $this->assertSame(['Hotline'], $this->previews('<div contenteditable="false"><p>Hotline</p></div>'));
        $this->assertSame([], $this->keys('<div contenteditable="false"><p>{{ $menu }}</p></div>'));
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

    public function test_it_honours_the_opt_out_marker_when_it_gives_a_reason(): void
    {
        $source = '<div><p '.EditableRegionScanner::IGNORE_ATTRIBUTE.'="breadcrumb sinh từ route">Trang chủ / Tin</p><p>Thật</p></div>';
        $audit = (new EditableRegionScanner)->audit($source, 'home');

        $this->assertSame(['Thật'], array_column($audit['findings'], 'preview'));
        $this->assertCount(1, $audit['ignored']);
        $this->assertSame('breadcrumb sinh từ route', $audit['ignored'][0]['reason']);
        $this->assertSame(1, $audit['counts']['text']['ignored']);
    }

    public function test_an_opt_out_without_a_reason_is_an_error_and_hides_nothing(): void
    {
        // The cheapest way to turn the audit green is to sprinkle the marker.
        // A bare one is therefore reported, and what it covers is still audited.
        foreach (['', '=""', '="   "'] as $value) {
            $source = '<div><p '.EditableRegionScanner::IGNORE_ATTRIBUTE.$value.'>Hotline 24/7</p></div>';
            $audit = (new EditableRegionScanner)->audit($source, 'home');

            $this->assertCount(1, $audit['invalidIgnores'], "marker `{$value}`");
            $this->assertSame([], $audit['ignored']);
            $this->assertSame(['Hotline 24/7'], array_column($audit['findings'], 'preview'));
            $this->assertFalse($audit['findings'][0]['fixable'], 'a half-made decision is not the fixer’s to finish');
        }
    }

    public function test_text_beside_a_layout_is_owned_by_its_own_element_not_the_cell(): void
    {
        // A cell holding a layout is not a sentence, so the cell is never the
        // region. The <div> holding the words is — reported for a human,
        // because it is not a tag --fix wraps mechanically.
        $findings = (new EditableRegionScanner)->scan('<table><tr><td><div class="x">Hộp</div></td></tr></table>', 'home');

        $this->assertCount(1, $findings);
        $this->assertSame('div', $findings[0]['tag']);
        $this->assertFalse($findings[0]['fixable']);
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

    /**
     * Every row here was reported as nothing by the tag-list scanner — and
     * every one is copy that themes routinely ship.
     */
    public function test_text_outside_the_classic_tags_is_found_and_left_for_a_human(): void
    {
        $cases = [
            '<div class="hero"><div class="hero__title">Giải pháp</div><p>x</p></div>' => ['div', 'Giải pháp'],
            '<section><span class="eyebrow">Về chúng tôi</span><h2>Về</h2></section>' => ['span', 'Về chúng tôi'],
            '<div class="hero"><a href="/lien-he" class="btn">Liên hệ ngay</a></div>' => ['a', 'Liên hệ ngay'],
            '<form><button type="submit">Gửi yêu cầu</button></form>' => ['button', 'Gửi yêu cầu'],
        ];

        foreach ($cases as $source => [$tag, $preview]) {
            $findings = array_values(array_filter(
                (new EditableRegionScanner)->scan($source, 'home'),
                static fn (array $f): bool => $f['preview'] === $preview,
            ));

            $this->assertCount(1, $findings, $source);
            $this->assertSame($tag, $findings[0]['tag'], $source);
            $this->assertFalse($findings[0]['fixable'], $source);
            $this->assertSame('manual', $findings[0]['reason'], $source);
        }
    }

    public function test_a_sentence_split_by_inline_tags_has_exactly_one_owner(): void
    {
        // Two owners for one sentence is the failure that makes a region
        // swallow half of its neighbour on the first save.
        $findings = (new EditableRegionScanner)->scan(
            '<section><div class="stat"><strong>500+</strong> khách hàng</div><div class="stat">x</div></section>',
            'home',
        );

        $this->assertSame(['500+ khách hàng', 'x'], array_column($findings, 'preview'));
        $this->assertSame(['div', 'div'], array_column($findings, 'tag'));
    }

    public function test_ownership_climbs_into_a_heading_or_link_but_not_a_plain_wrapper(): void
    {
        $this->assertSame(['h2'], array_column((new EditableRegionScanner)->scan('<h2><span>Dịch vụ</span></h2>', 'home'), 'tag'));
        $this->assertSame(['a'], array_column((new EditableRegionScanner)->scan('<a class="btn"><span>Liên hệ</span></a>', 'home'), 'tag'));

        // Two CTAs side by side are two regions, not one region holding both.
        $this->assertSame(
            ['a', 'a'],
            array_column((new EditableRegionScanner)->scan('<div class="actions"><a href="#">A</a><a href="#">B</a></div>', 'home'), 'tag'),
        );
    }

    public function test_text_mixed_with_block_children_has_no_owner_to_wrap(): void
    {
        $findings = (new EditableRegionScanner)->scan('<div class="x">Chữ trần <div>khối</div></div>', 'home');
        $mixed = array_values(array_filter($findings, static fn (array $f): bool => $f['reason'] === 'mixed'));

        $this->assertCount(1, $mixed);
        $this->assertSame('Chữ trần', $mixed[0]['preview']);
        $this->assertSame('div', $mixed[0]['container']);
        $this->assertFalse($mixed[0]['fixable']);
    }

    public function test_a_doctype_is_not_copy(): void
    {
        $this->assertSame([], $this->keys('<!doctype html><html><body><p>{{ $x }}</p></body></html>'));
    }

    public function test_responsive_lazy_and_background_pictures_are_editable_but_converted_by_hand(): void
    {
        // The editor can replace all of these now, but only with the theme's own markup carried
        // over (srcset, <source>, placeholder), so --fix leaves them to a person.
        $cases = [
            '<div data-bg="/client-assets/img/bg.jpg"></div>' => 'data-background',
            '<picture><source srcset="/a.webp"><img src="/a.jpg" alt=""></picture>' => 'picture',
            '<img src="/a.jpg" srcset="/a-2x.jpg 2x" alt="">' => 'srcset',
            '<img src="data:image/gif;base64,R0lG" data-src="/real.jpg" alt="">' => 'lazy',
            '<section style="background: linear-gradient(#000, #fff), url(/a.jpg)"></section>' => 'layered',
        ];

        foreach ($cases as $source => $reason) {
            $findings = (new EditableRegionScanner)->scan($source, 'home');

            $this->assertCount(1, $findings, $source);
            $this->assertSame(EditableRegionScanner::STATUS_UNKNOWN, $findings[0]['status'], $source);
            $this->assertSame($reason, $findings[0]['reason'], $source);
            $this->assertFalse($findings[0]['fixable'], $source);
        }
    }

    public function test_a_video_poster_is_still_unsupported(): void
    {
        $findings = (new EditableRegionScanner)->scan('<video poster="/poster.jpg"></video>', 'home');

        $this->assertSame(EditableRegionScanner::STATUS_UNSUPPORTED, $findings[0]['status']);
        $this->assertSame('poster', $findings[0]['reason']);
        $this->assertFalse($findings[0]['fixable']);
    }

    public function test_only_a_provably_safe_background_is_offered_to_fix(): void
    {
        $fixable = static fn (string $source): bool => (new EditableRegionScanner)->scan($source, 'home')[0]['fixable'];

        $this->assertTrue($fixable('<section class="hero" style="background-image:url(/client-assets/img/bg.jpg)"></section>'));
        $this->assertTrue($fixable("<div style=\"background: url('/a.jpg') center/cover no-repeat\"></div>"));

        // Blade in the style or a slider around it: a person decides.
        $this->assertFalse($fixable("<div style=\"background-image:url({{ asset('a.jpg') }})\"></div>"));
        $this->assertFalse($fixable('<div class="swiper"><div class="swiper-slide" style="background-image:url(/a.jpg)"></div></div>'));
        $this->assertFalse($fixable('<div class="owl-carousel"><div style="background-image:url(/a.jpg)"></div></div>'));
    }

    public function test_a_marked_background_counts_as_editable_and_its_children_are_still_scanned(): void
    {
        $source = <<<'BLADE'
        <section class="hero" @clientBackground('home.hero.bg', 'background-image:url(/a.jpg)')>
            <x-client::editable key="home.hero.title" tag="h1">Tiêu đề</x-client::editable>
            <p>Chưa đánh dấu</p>
        </section>
        BLADE;

        $findings = (new EditableRegionScanner)->scan($source, 'home');

        $this->assertSame(['Chưa đánh dấu'], array_column($findings, 'preview'));
    }

    public function test_a_style_attribute_beside_client_background_is_reported(): void
    {
        // Two style attributes: the browser keeps the first and the background never shows.
        $findings = (new EditableRegionScanner)->scan(
            "<section style=\"padding:40px\" @clientBackground('home.hero.bg', 'background-image:url(/a.jpg)')></section>",
            'home',
        );

        $this->assertCount(1, $findings);
        $this->assertSame('background-style-conflict', $findings[0]['reason']);
        $this->assertSame(EditableRegionScanner::STATUS_UNKNOWN, $findings[0]['status']);
        $this->assertFalse($findings[0]['fixable']);
    }

    public function test_keys_already_written_are_never_handed_out_again(): void
    {
        $source = <<<'BLADE'
        <section @clientBackground('pages.home.main.section-1', 'background-image:url(/a.jpg)')></section>
        <x-client::editable key="pages.home.main.h2-1" tag="h2">Đã sửa</x-client::editable>
        <section style="background-image:url(/b.jpg)"></section>
        <h2>Mới</h2>
        BLADE;

        $keys = array_column((new EditableRegionScanner)->scan($source, 'pages.home'), 'key');

        $this->assertSame(['pages.home.main.section-2', 'pages.home.main.h2-2'], $keys);
    }

    public function test_a_lazy_image_reports_the_real_source_not_the_placeholder(): void
    {
        $findings = (new EditableRegionScanner)->scan('<img src="data:image/gif;base64,R0lG" data-src="/real.jpg" alt="">', 'home');

        $this->assertSame('/real.jpg', $findings[0]['preview']);
    }

    public function test_image_sources_from_a_record_are_dynamic(): void
    {
        $audit = (new EditableRegionScanner)->audit(
            '<section style="background-image:url({{ $banner->url }})"></section><img data-src="{{ $p->image }}" src="x.gif">',
            'home',
        );

        $this->assertSame([], $audit['findings']);
        $this->assertSame(2, $audit['counts']['image']['dynamic']);
    }

    public function test_an_ignore_reason_covers_images_too(): void
    {
        $audit = (new EditableRegionScanner)->audit(
            '<section data-editable-ignore="ảnh nền trang trí" style="background:url(/bg.jpg)"></section>',
            'home',
        );

        $this->assertSame([], $audit['findings']);
        $this->assertSame(1, $audit['counts']['image']['ignored']);
    }

    public function test_a_plain_theme_image_is_still_auto_fixable(): void
    {
        $findings = (new EditableRegionScanner)->scan('<div class="hero"><img src="/a.jpg" alt="A"></div>', 'home');

        $this->assertTrue($findings[0]['fixable']);
        $this->assertSame(EditableRegionScanner::STATUS_UNKNOWN, $findings[0]['status']);
    }

    public function test_marked_regions_are_counted_as_editable(): void
    {
        $audit = (new EditableRegionScanner)->audit(
            '<x-client::editable key="a.b.c" tag="p">Chữ</x-client::editable><x-client::editable-image key="a.b.d" src="/a.jpg" />',
            'home',
        );

        $this->assertSame([], $audit['findings']);
        $this->assertSame(1, $audit['counts']['text']['editable']);
        $this->assertSame(1, $audit['counts']['image']['editable']);
    }

    public function test_a_header_partial_is_audited_like_any_other_view(): void
    {
        // partials/ used to be skipped wholesale; this is the markup that hid there.
        $source = <<<'BLADE'
        <header class="site-header">
            <a class="brand" href="/">{{ $siteBranding['name'] }}</a>
            <div class="hotline"><span>Hotline:</span> {{ $settings['phone'] }}</div>
            <a class="btn" href="/lien-he">Nhận báo giá</a>
            @include('client.partials.navigation')
        </header>
        BLADE;

        // The label is its own element, so it is copy; the number beside it is
        // a setting and stays dynamic.
        $this->assertSame(['Hotline:', 'Nhận báo giá'], $this->previews($source, 'partials.header'));
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
