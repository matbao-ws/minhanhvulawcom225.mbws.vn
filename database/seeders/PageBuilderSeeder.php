<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\PageBuilderService;
use Illuminate\Database\Seeder;

class PageBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PageBuilderService::class);
        $created = 0;

        foreach ($this->pages() as $pageData) {
            if (Page::query()->withTrashed()->where('slug', $pageData['slug']['vi'])->exists()) {
                continue;
            }

            $service->create($pageData);
            $created++;
        }

        $this->command?->info("Page Builder: đã tạo {$created} trang mẫu.");
    }

    private function pages(): array
    {
        $sharedCss = <<<'CSS'
.demo-page{font-family:Arial,sans-serif;color:#172033;line-height:1.65}
.demo-page h1,.demo-page h2,.demo-page h3{line-height:1.2;margin-top:0}
.demo-page .demo-container{max-width:1120px;margin:0 auto;padding-left:24px;padding-right:24px}
.demo-page .demo-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.demo-page .demo-card{padding:28px;border:1px solid #e6eaf0;border-radius:16px;background:#fff;box-shadow:0 12px 32px rgba(23,32,51,.06)}
.demo-page .demo-button{display:inline-block;padding:13px 24px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700}
.demo-page .demo-button-light{background:#fff;color:#1d4ed8}
.demo-page .demo-icon{width:42px;height:42px;color:#2563eb;margin-bottom:16px}
@media(max-width:767px){.demo-page .demo-grid{grid-template-columns:1fr}.demo-page h1{font-size:38px!important}}
CSS;

        return [
            [
                'title' => ['vi' => 'Giới thiệu', 'en' => 'About us'],
                'slug' => ['vi' => 'gioi-thieu', 'en' => 'about-us'],
                'meta_title' => ['vi' => 'Giới thiệu về cửa hàng', 'en' => 'About our store'],
                'meta_description' => [
                    'vi' => 'Câu chuyện, giá trị và cam kết của cửa hàng.',
                    'en' => 'Our story, values, and commitment.',
                ],
                'builder_data' => ['version' => 1, 'locales' => []],
                'published_html' => [
                    'vi' => $this->aboutHtml(
                        'Đồng hành cùng trải nghiệm mua sắm tốt hơn',
                        'Chúng tôi xây dựng cửa hàng bằng sản phẩm được chọn lọc, dịch vụ minh bạch và sự tận tâm trong từng đơn hàng.',
                        'Khám phá sản phẩm',
                        'Câu chuyện của chúng tôi',
                        'Bắt đầu từ mong muốn giúp khách hàng mua sắm đơn giản và an tâm hơn, đội ngũ luôn ưu tiên chất lượng, tốc độ và trải nghiệm sau bán hàng.',
                        ['Sản phẩm chọn lọc', 'Giao hàng tận tâm', 'Hỗ trợ rõ ràng'],
                        'Sẵn sàng tìm sản phẩm phù hợp?',
                        'Xem cửa hàng',
                    ),
                    'en' => $this->aboutHtml(
                        'Building a better shopping experience',
                        'We build our store around carefully selected products, transparent service, and care in every order.',
                        'Explore products',
                        'Our story',
                        'We started with a simple goal: make shopping easier and more reliable through quality, speed, and thoughtful after-sales support.',
                        ['Curated products', 'Reliable delivery', 'Clear support'],
                        'Ready to find your next favorite?',
                        'Visit the store',
                    ),
                ],
                'published_css' => ['vi' => $sharedCss, 'en' => $sharedCss],
                'is_active' => true,
            ],
            [
                'title' => ['vi' => 'Chính sách giao hàng', 'en' => 'Shipping policy'],
                'slug' => ['vi' => 'chinh-sach-giao-hang', 'en' => 'shipping-policy'],
                'meta_title' => ['vi' => 'Chính sách giao hàng', 'en' => 'Shipping policy'],
                'meta_description' => [
                    'vi' => 'Thông tin khu vực, thời gian và quy trình giao nhận.',
                    'en' => 'Delivery areas, timelines, and receiving process.',
                ],
                'builder_data' => ['version' => 1, 'locales' => []],
                'published_html' => [
                    'vi' => $this->policyHtml(
                        'Chính sách giao hàng',
                        'Thông tin rõ ràng để bạn chủ động theo dõi và nhận đơn.',
                        [
                            ['Khu vực giao hàng', 'Chúng tôi giao hàng toàn quốc qua các đối tác vận chuyển phù hợp với từng khu vực.'],
                            ['Thời gian dự kiến', 'Nội thành từ 1–3 ngày làm việc; các tỉnh thành khác từ 3–7 ngày làm việc.'],
                            ['Kiểm tra khi nhận', 'Vui lòng kiểm tra tình trạng bao bì và đối chiếu sản phẩm trước khi xác nhận nhận hàng.'],
                        ],
                        'Cần hỗ trợ về đơn hàng?',
                        'Liên hệ đội ngũ chăm sóc khách hàng để được kiểm tra trạng thái vận chuyển.',
                    ),
                    'en' => $this->policyHtml(
                        'Shipping policy',
                        'Clear information so you can track and receive your order with confidence.',
                        [
                            ['Delivery coverage', 'We deliver nationwide through shipping partners selected for each area.'],
                            ['Estimated timeline', 'Major cities take 1–3 business days; other locations take 3–7 business days.'],
                            ['Receiving your order', 'Please check the package condition and items before confirming receipt.'],
                        ],
                        'Need help with an order?',
                        'Contact our customer care team to check your delivery status.',
                    ),
                ],
                'published_css' => ['vi' => $sharedCss, 'en' => $sharedCss],
                'is_active' => true,
            ],
            [
                'title' => ['vi' => 'Liên hệ', 'en' => 'Contact'],
                'slug' => ['vi' => 'lien-he', 'en' => 'contact'],
                'meta_title' => ['vi' => 'Liên hệ cửa hàng', 'en' => 'Contact our store'],
                'meta_description' => [
                    'vi' => 'Các kênh hỗ trợ và thời gian làm việc của cửa hàng.',
                    'en' => 'Store support channels and working hours.',
                ],
                'builder_data' => ['version' => 1, 'locales' => []],
                'published_html' => [
                    'vi' => $this->contactHtml(
                        'Chúng tôi luôn sẵn sàng hỗ trợ',
                        'Chọn kênh thuận tiện nhất, đội ngũ sẽ phản hồi trong thời gian sớm nhất.',
                        [
                            ['Hotline', '0900 000 000', 'Thứ 2 – Thứ 7, 08:00 – 18:00'],
                            ['Email', 'support@example.com', 'Phản hồi trong vòng 24 giờ làm việc'],
                            ['Địa chỉ', '123 Đường Mẫu, TP. Hồ Chí Minh', 'Vui lòng hẹn trước khi đến'],
                        ],
                    ),
                    'en' => $this->contactHtml(
                        'We are always here to help',
                        'Choose the channel that works best for you and our team will respond as soon as possible.',
                        [
                            ['Hotline', '0900 000 000', 'Monday – Saturday, 08:00 – 18:00'],
                            ['Email', 'support@example.com', 'Replies within one business day'],
                            ['Address', '123 Sample Street, Ho Chi Minh City', 'Please arrange an appointment before visiting'],
                        ],
                    ),
                ],
                'published_css' => ['vi' => $sharedCss, 'en' => $sharedCss],
                'is_active' => true,
            ],
        ];
    }

    private function aboutHtml(
        string $headline,
        string $lead,
        string $primaryAction,
        string $storyTitle,
        string $story,
        array $values,
        string $ctaTitle,
        string $ctaAction,
    ): string {
        $cards = collect($values)->map(function (string $value, int $index): string {
            $icons = [
                '<path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3z"></path>',
                '<path d="M3 7h11v10H3z"></path><path d="M14 10h4l3 3v4h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle>',
                '<path d="M4 5h16v12H8l-4 4z"></path><path d="M8 9h8M8 13h5"></path>',
            ];

            return '<article class="demo-card"><svg class="demo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">'.$icons[$index].'</svg><h3>'.$value.'</h3><p>Cam kết được duy trì trong từng trải nghiệm và mỗi đơn hàng.</p></article>';
        })->implode('');

        return <<<HTML
<main class="demo-page">
  <section data-page-block="hero" style="padding:96px 0;background:linear-gradient(135deg,#eff6ff,#eef2ff);text-align:center">
    <div class="demo-container"><small style="font-weight:700;color:#2563eb;letter-spacing:.12em;text-transform:uppercase">Welcome</small><h1 style="font-size:54px;max-width:850px;margin:16px auto 20px">{$headline}</h1><p style="font-size:20px;max-width:720px;margin:0 auto 30px;color:#526078">{$lead}</p><a class="demo-button" href="/products">{$primaryAction}</a></div>
  </section>
  <section data-page-block="rich-text" style="padding:76px 0"><div class="demo-container" style="max-width:820px;text-align:center"><h2 style="font-size:38px">{$storyTitle}</h2><p style="font-size:18px;color:#526078">{$story}</p></div></section>
  <section data-page-block="values" style="padding:0 0 76px"><div class="demo-container"><div class="demo-grid">{$cards}</div></div></section>
  <section data-page-block="cta" style="padding:68px 0;background:#172033;color:#fff;text-align:center"><div class="demo-container"><h2 style="font-size:38px;color:#fff">{$ctaTitle}</h2><a class="demo-button demo-button-light" href="/products">{$ctaAction}</a></div></section>
</main>
HTML;
    }

    private function policyHtml(string $title, string $lead, array $items, string $ctaTitle, string $ctaText): string
    {
        $cards = collect($items)->map(fn (array $item, int $index): string => '<article class="demo-card"><div style="width:42px;height:42px;border-radius:50%;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-weight:800;margin-bottom:16px">'.($index + 1).'</div><h2 style="font-size:22px">'.$item[0].'</h2><p style="color:#526078;margin-bottom:0">'.$item[1].'</p></article>')->implode('');

        return <<<HTML
<main class="demo-page">
  <section data-page-block="hero" style="padding:84px 0;background:#172033;color:#fff;text-align:center"><div class="demo-container"><h1 style="font-size:50px;color:#fff">{$title}</h1><p style="font-size:19px;max-width:680px;margin:0 auto;color:#cbd5e1">{$lead}</p></div></section>
  <section data-page-block="policy-steps" style="padding:72px 0;background:#f8fafc"><div class="demo-container"><div class="demo-grid">{$cards}</div></div></section>
  <section data-page-block="cta" style="padding:64px 0;text-align:center"><div class="demo-container" style="max-width:760px"><h2>{$ctaTitle}</h2><p style="color:#526078">{$ctaText}</p><a class="demo-button" href="/contact">Contact</a></div></section>
</main>
HTML;
    }

    private function contactHtml(string $title, string $lead, array $items): string
    {
        $cards = collect($items)->map(fn (array $item): string => '<article class="demo-card"><h2 style="font-size:22px;color:#2563eb">'.$item[0].'</h2><p style="font-size:18px;font-weight:700;margin-bottom:8px">'.$item[1].'</p><p style="color:#526078;margin-bottom:0">'.$item[2].'</p></article>')->implode('');

        return <<<HTML
<main class="demo-page">
  <section data-page-block="hero" style="padding:90px 0;background:linear-gradient(135deg,#eff6ff,#f5f3ff);text-align:center"><div class="demo-container"><h1 style="font-size:52px">{$title}</h1><p style="font-size:19px;max-width:680px;margin:0 auto;color:#526078">{$lead}</p></div></section>
  <section data-page-block="contact-channels" style="padding:76px 0"><div class="demo-container"><div class="demo-grid">{$cards}</div></div></section>
</main>
HTML;
    }
}
