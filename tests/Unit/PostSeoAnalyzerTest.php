<?php

namespace Tests\Unit;

use App\Services\PostSeoAnalyzer;
use Tests\TestCase;

class PostSeoAnalyzerTest extends TestCase
{
    public function test_it_scores_an_optimized_vietnamese_post_on_the_server(): void
    {
        $paragraph = '<p>'.implode(' ', array_fill(0, 50, 'nội dung')).'</p>';
        $analysis = app(PostSeoAnalyzer::class)->analyze([
            'title' => 'Hướng dẫn tối ưu SEO bài viết cho website hiệu quả',
            'slug' => 'huong-dan-toi-uu-seo-bai-viet',
            'seo_title' => 'Tối ưu SEO bài viết: 7 bước hiệu quả cho website',
            'seo_description' => 'Tối ưu SEO bài viết đúng cách với hướng dẫn chi tiết về tiêu đề, nội dung, liên kết và từ khóa giúp website tiếp cận đúng khách hàng mục tiêu.',
            'focus_keyword' => 'tối ưu SEO, viết bài chuẩn SEO',
            'content' => '<p>Tối ưu SEO là bước đầu tiên.</p>'.str_repeat($paragraph, 6)
                .'<h2>Tối ưu SEO cho cấu trúc bài viết</h2>'
                .'<p>Hai bước tối ưu SEO nội dung. <a href="/san-pham">Xem sản phẩm</a> và <a href="https://developers.google.com/search">hướng dẫn của Google</a>.</p>'
                .'<p><img src="/image.jpg" alt="Tối ưu SEO"></p>',
        ]);

        $this->assertEqualsCanonicalizing(array_keys(PostSeoAnalyzer::WEIGHTS), array_keys(array_filter($analysis['rules'])), 'every rule passes');
        $this->assertSame(100, $analysis['score']);
        $this->assertSame('good', $analysis['status']);
        $this->assertGreaterThanOrEqual(300, $analysis['metrics']['word_count']);
    }

    public function test_the_weights_add_up_to_a_hundred(): void
    {
        $this->assertSame(100, array_sum(PostSeoAnalyzer::WEIGHTS));
    }

    public function test_it_reports_missing_seo_requirements_without_trusting_a_client_score(): void
    {
        $analysis = app(PostSeoAnalyzer::class)->analyze([
            'title' => 'Bài ngắn',
            'slug' => 'bai-ngan',
            'content' => '<p>Ít nội dung</p><img src="/image.jpg">',
        ]);

        // Only what needs no keyword passes: a short URL, short paragraphs, an image.
        $this->assertEqualsCanonicalizing(['url_length', 'short_paragraphs', 'content_media'], array_keys(array_filter($analysis['rules'])));
        $this->assertSame(12, $analysis['score']);
        $this->assertSame('needs_work', $analysis['status']);
        $this->assertFalse($analysis['rules']['image_alts']);
    }

    public function test_long_paragraphs_links_out_and_where_the_keyword_sits_are_checked(): void
    {
        $analyzer = app(PostSeoAnalyzer::class);
        $base = ['title' => 'x', 'slug' => 'giay-chay-bo', 'focus_keyword' => 'giày chạy bộ'];

        $wall = $analyzer->analyze($base + ['content' => '<p>'.str_repeat('chữ ', 160).'</p>', 'seo_title' => 'Mẹo hay khi chọn mua giày chạy bộ']);
        $this->assertFalse($wall['rules']['short_paragraphs']);
        $this->assertFalse($wall['rules']['title_keyword_start'], 'keyword in the second half of the title');
        $this->assertFalse($wall['rules']['external_links']);

        $good = $analyzer->analyze($base + ['content' => '<p>Ngắn. <a href="https://www.runnersworld.com/">Nguồn</a></p>', 'seo_title' => 'Giày chạy bộ: mẹo hay khi chọn mua']);
        $this->assertTrue($good['rules']['short_paragraphs']);
        $this->assertTrue($good['rules']['title_keyword_start']);
        $this->assertTrue($good['rules']['external_links']);
        $this->assertFalse($analyzer->analyze($base + ['content' => '<a href="/noi-bo">x</a>'])['rules']['external_links'], 'an internal link is not an outside source');
    }
}
