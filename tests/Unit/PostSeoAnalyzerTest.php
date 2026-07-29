<?php

namespace Tests\Unit;

use App\Services\PostSeoAnalyzer;
use Tests\TestCase;

class PostSeoAnalyzerTest extends TestCase
{
    public function test_it_scores_an_optimized_vietnamese_post_on_the_server(): void
    {
        $filler = implode(' ', array_fill(0, 300, 'nội dung'));
        $analysis = app(PostSeoAnalyzer::class)->analyze([
            'title' => 'Hướng dẫn tối ưu SEO bài viết cho website hiệu quả',
            'slug' => 'huong-dan-toi-uu-seo-bai-viet',
            'seo_title' => 'Hướng dẫn tối ưu SEO bài viết hiệu quả năm 2026',
            'seo_description' => 'Tối ưu SEO bài viết đúng cách với hướng dẫn chi tiết về tiêu đề, nội dung, liên kết và từ khóa giúp website tiếp cận đúng khách hàng mục tiêu.',
            'focus_keyword' => 'tối ưu SEO',
            'content' => '<p>Tối ưu SEO là bước đầu tiên. '.$filler.' tối ưu SEO nội dung tối ưu SEO.</p>'
                .'<h2>Cấu trúc bài viết</h2>'
                .'<p><a href="/san-pham">Xem sản phẩm</a><img src="/image.jpg" alt="Tối ưu SEO"></p>',
        ]);

        $this->assertSame(100, $analysis['score']);
        $this->assertSame('good', $analysis['status']);
        $this->assertTrue($analysis['rules']['internal_links']);
        $this->assertGreaterThanOrEqual(300, $analysis['metrics']['word_count']);
    }

    public function test_it_reports_missing_seo_requirements_without_trusting_a_client_score(): void
    {
        $analysis = app(PostSeoAnalyzer::class)->analyze([
            'title' => 'Bài ngắn',
            'slug' => 'bai-ngan',
            'content' => '<p>Ít nội dung</p><img src="/image.jpg">',
        ]);

        $this->assertSame(0, $analysis['score']);
        $this->assertSame('needs_work', $analysis['status']);
        $this->assertFalse($analysis['rules']['keyword_exists']);
        $this->assertFalse($analysis['rules']['image_alts']);
    }
}
