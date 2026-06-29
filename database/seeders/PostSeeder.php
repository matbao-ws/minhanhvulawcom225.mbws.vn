<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Post::truncate();
        PostCategory::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $categories = [
            [
                'name' => ['vi' => 'Tin tức', 'en' => 'News'],
                'slug' => 'tin-tuc',
                'description' => ['vi' => 'Cập nhật tin tức mới nhất về thương mại điện tử và công nghệ.', 'en' => 'Latest updates about e-commerce and technology.'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => ['vi' => 'Hướng dẫn', 'en' => 'Guides'],
                'slug' => 'huong-dan',
                'description' => ['vi' => 'Các bài viết hướng dẫn sử dụng và tối ưu hóa cửa hàng bán hàng.', 'en' => 'Step-by-step guides to optimize your online store.'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => ['vi' => 'Đánh giá', 'en' => 'Reviews'],
                'slug' => 'danh-gia',
                'description' => ['vi' => 'Đánh giá chi tiết các tính năng, giải pháp và ứng dụng hỗ trợ.', 'en' => 'Detailed reviews of tools, features, and third-party integrations.'],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[] = PostCategory::create($cat);
        }

        $newsCat = $categoryModels[0];
        $guideCat = $categoryModels[1];
        $reviewCat = $categoryModels[2];

        $subNewsDomestic = PostCategory::create([
            'parent_id' => $newsCat->id,
            'name' => ['vi' => 'Tin tức Trong nước', 'en' => 'Domestic News'],
            'slug' => 'tin-tuc-trong-nuoc',
            'description' => ['vi' => 'Tin tức thương mại điện tử trong nước.', 'en' => 'Domestic e-commerce news.'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subNewsInternational = PostCategory::create([
            'parent_id' => $newsCat->id,
            'name' => ['vi' => 'Tin tức Quốc tế', 'en' => 'International News'],
            'slug' => 'tin-tuc-quoc-te',
            'description' => ['vi' => 'Tin tức thương mại điện tử toàn cầu.', 'en' => 'Global e-commerce news.'],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $subGuidesTech = PostCategory::create([
            'parent_id' => $guideCat->id,
            'name' => ['vi' => 'Hướng dẫn Kỹ thuật', 'en' => 'Technical Guides'],
            'slug' => 'huong-dan-ky-thuat',
            'description' => ['vi' => 'Hướng dẫn kỹ thuật, code và cấu hình.', 'en' => 'Technical guides, coding and configurations.'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $posts = [
            [
                'category_id' => $newsCat->id,
                'title' => [
                    'vi' => 'Xu hướng thương mại điện tử nổi bật năm 2026',
                    'en' => 'Top E-commerce Trends to Watch in 2026',
                ],
                'slug' => 'xu-huong-thuong-mai-dien-tu-2026',
                'summary' => [
                    'vi' => 'Khám phá các xu hướng thương mại điện tử mới nổi giúp đột phá doanh thu của bạn.',
                    'en' => 'Explore emerging e-commerce trends that will skyrocket your revenue.',
                ],
                'content' => [
                    'vi' => '<p>Thương mại điện tử năm 2026 đang chứng kiến sự trỗi dậy mạnh mẽ của công nghệ AI cá nhân hóa và thanh toán tức thời.</p><h2>1. Trí tuệ nhân tạo (AI) cá nhân hóa</h2><p>Các công cụ AI hiện nay có khả năng phân tích hành vi của khách hàng theo thời gian thực để đưa ra các gợi ý sản phẩm cực kỳ chính xác.</p><h2>2. Trải nghiệm mua sắm đa kênh (Omnichannel)</h2><p>Khách hàng mong muốn chuyển đổi mượt mà giữa cửa hàng vật lý, mạng xã hội và website của bạn.</p>',
                    'en' => '<p>E-commerce in 2026 is experiencing the rise of personalized AI technology and instant payments.</p><h2>1. Personalized AI</h2><p>AI tools can analyze customer behavior in real-time to recommend products with high precision.</p><h2>2. Omnichannel Shopping Experience</h2><p>Customers want a seamless transition between physical stores, social networks, and your website.</p>',
                ],
                'image_url' => null,
                'is_active' => true,
                'seo_title' => [
                    'vi' => 'Xu hướng thương mại điện tử năm 2026 | Báo cáo chi tiết',
                    'en' => 'Top E-commerce Trends in 2026 | Full Report',
                ],
                'seo_description' => [
                    'vi' => 'Xu hướng thương mại điện tử 2026 mới nhất giúp cửa hàng trực tuyến tối ưu doanh thu bán hàng đa kênh nhờ AI.',
                    'en' => 'Discover the latest 2026 e-commerce trends to optimize omnichannel store sales using AI.',
                ],
                'seo_keys' => 'thương mại điện tử',
                'published_at' => now(),
            ],
            [
                'category_id' => $guideCat->id,
                'title' => [
                    'vi' => 'Hướng dẫn viết bài viết chuẩn SEO cho người mới bắt đầu',
                    'en' => 'How to Write SEO-Friendly Articles for Beginners',
                ],
                'slug' => 'huong-dan-viet-bai-chuan-seo',
                'summary' => [
                    'vi' => 'Từng bước tối ưu nội dung để bài viết của bạn đạt thứ hạng cao trên công cụ tìm kiếm Google.',
                    'en' => 'Step-by-step optimization guide to rank your articles on Google search results.',
                ],
                'content' => [
                    'vi' => '<p>Viết bài viết chuẩn SEO không chỉ là chèn từ khóa mà là tạo ra nội dung giá trị cho người đọc.</p><h2>Bước 1: Nghiên cứu từ khóa chính</h2><p>Tìm từ khóa mà người dùng thực tế tìm kiếm bằng các công cụ như Google Keyword Planner.</p><h2>Bước 2: Phân bố từ khóa chuẩn SEO</h2><p>Đặt từ khóa chính trong tiêu đề H1, thẻ H2, mô tả SEO và đoạn mở đầu.</p>',
                    'en' => '<p>Writing SEO-friendly articles is not just keyword stuffing, it is about creating value for readers.</p><h2>Step 1: Keyword Research</h2><p>Find keywords that users actually search for using tools like Google Keyword Planner.</p><h2>Step 2: Optimize Keyword Placement</h2><p>Place your focus keyword in the H1 title, H2 headings, Meta description, and slug.</p>',
                ],
                'image_url' => null,
                'is_active' => true,
                'seo_title' => [
                    'vi' => 'Hướng dẫn viết bài chuẩn SEO từ A-Z năm 2026',
                    'en' => 'Complete SEO Writing Guide for Beginners',
                ],
                'seo_description' => [
                    'vi' => 'Học cách viết bài viết chuẩn SEO tối ưu hóa tiêu đề, thẻ heading, mật độ từ khóa và hình ảnh alt hiệu quả.',
                    'en' => 'Learn how to write SEO-friendly articles by optimizing title, heading tags, density, and image alt.',
                ],
                'seo_keys' => 'chuẩn SEO',
                'published_at' => now()->subDays(1),
            ],
            [
                'category_id' => $reviewCat->id,
                'title' => [
                    'vi' => 'Đánh giá tính năng giỏ hàng nâng cao cho website bán hàng',
                    'en' => 'Advanced Shopping Cart Feature Review for E-commerce Sites',
                ],
                'slug' => 'danh-gia-gio-hang-nang-cao',
                'summary' => [
                    'vi' => 'Phân tích hiệu quả của tính năng giỏ hàng Ajax giúp giảm tỷ lệ bỏ rơi giỏ hàng của người dùng.',
                    'en' => 'Analyzing the performance of Ajax shopping cart in reducing checkout abandonment rates.',
                ],
                'content' => [
                    'vi' => '<p>Giỏ hàng nâng cao sử dụng công nghệ Ajax giúp người mua thêm sản phẩm tức thì mà không cần tải lại trang.</p><h2>Ưu điểm nổi bật</h2><p>Tốc độ xử lý siêu tốc, giao diện tối giản và hỗ trợ tự động gợi ý mã giảm giá khi thanh toán.</p><h2>Nhược điểm</h2><p>Cần tích hợp JS cẩn thận để tránh lỗi xung đột thư viện bên thứ ba.</p>',
                    'en' => '<p>Advanced shopping carts utilizing Ajax allow buyers to add items instantly without reload.</p><h2>Pros</h2><p>Lightning-fast speed, minimal layout, and automated voucher recommendations at checkout.</p><h2>Cons</h2><p>Requires careful JS integration to avoid third-party script conflicts.</p>',
                ],
                'image_url' => null,
                'is_active' => true,
                'seo_title' => [
                    'vi' => 'Đánh giá tính năng giỏ hàng Ajax nâng cao',
                    'en' => 'Ajax Shopping Cart Feature Review',
                ],
                'seo_description' => [
                    'vi' => 'Xem đánh giá chi tiết tính năng giỏ hàng Ajax nâng cao giúp tăng tỷ lệ chuyển đổi cho website thương mại điện tử.',
                    'en' => 'Read our detailed review of Ajax shopping cart to increase conversion rates for your e-commerce site.',
                ],
                'seo_keys' => 'giỏ hàng',
                'published_at' => now()->subDays(2),
            ],
        ];

        foreach ($posts as $postData) {
            Post::create($postData);
        }

        // Add more simple posts to easily test pagination (bringing total to 28)
        foreach (range(4, 28) as $i) {
            Post::create([
                'category_id' => $i % 3 === 0 ? $subNewsDomestic->id : ($i % 3 === 1 ? $subNewsInternational->id : $subGuidesTech->id),
                'title' => [
                    'vi' => 'Bài viết tin tức số ' . $i,
                    'en' => 'News Post Number ' . $i,
                ],
                'slug' => 'bai-viet-tin-tuc-so-' . $i,
                'summary' => [
                    'vi' => 'Tóm tắt bài viết số ' . $i,
                    'en' => 'Summary of post number ' . $i,
                ],
                'content' => [
                    'vi' => '<p>Nội dung chi tiết bài viết số ' . $i . ' cho mục tin tức chung.</p>',
                    'en' => '<p>Detailed content of post number ' . $i . ' for general news.</p>',
                ],
                'is_active' => $i % 4 !== 0,
                'seo_title' => [
                    'vi' => 'Tiêu đề SEO bài viết ' . $i,
                    'en' => 'SEO Title of Post ' . $i,
                ],
                'seo_description' => [
                    'vi' => 'Mô tả SEO bài viết ' . $i . ' giúp tối ưu hóa công cụ tìm kiếm chuẩn SEO.',
                    'en' => 'SEO description of post ' . $i . ' for search engines optimization.',
                ],
                'seo_keys' => 'bài viết',
                'published_at' => now()->subDays($i),
            ]);
        }
    }
}
