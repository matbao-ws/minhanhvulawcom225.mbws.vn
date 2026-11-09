<?php

namespace Theme;

use App\Models\FeatureSetting;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\ProjectSetting;
use App\Services\LocalizedSlugService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent theme data seeder for Công ty Luật TNHH MTV Minh Anh Vũ (minhanhvulawcom225.mbws.vn).
 * Stored under theme/ so CoreManifest::CORE (database/seeders/**) remains 100% untouched.
 */
class MinhAnhVuThemeSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('project_settings')) {
            return;
        }

        $slugService = app(LocalizedSlugService::class);

        DB::transaction(function () use ($slugService): void {
            $this->seedFeatures();
            $this->seedSettings();
            $categories = $this->seedPostCategories($slugService);
            $this->seedPosts($categories, $slugService);
            $this->seedPages($slugService);
            $this->seedPartners();
            $this->seedMenus();
        });
    }

    private function seedFeatures(): void
    {
        if (! Schema::hasTable('feature_settings')) {
            return;
        }

        foreach (['cms_page', 'post', 'partner', 'menu', 'contact', 'catalog'] as $code) {
            FeatureSetting::query()->updateOrCreate(
                ['feature_code' => $code],
                ['is_enabled' => true]
            );
        }
    }

    private function seedSettings(): void
    {
        $shopName = ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'shop_name'],
            ['setting_value' => 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ', 'updated_at' => now()]
        );
        if (in_array($shopName->getAttribute('setting_value'), [null, '', 'Laravel Ecommerce Core', 'Minh Anh Vũ Law'], true)) {
            $shopName->update(['setting_value' => 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ']);
        }

        $logo = ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'logo_url'],
            ['setting_value' => '/assets/uploads/2024/10/cropped-favicon-192x192.png', 'updated_at' => now()]
        );
        if (empty($logo->getAttribute('setting_value'))) {
            $logo->update(['setting_value' => '/assets/uploads/2024/10/cropped-favicon-192x192.png']);
        }

        $favicon = ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'favicon_url'],
            ['setting_value' => '/assets/uploads/2024/10/cropped-favicon-32x32.png', 'updated_at' => now()]
        );
        if (empty($favicon->getAttribute('setting_value'))) {
            $favicon->update(['setting_value' => '/assets/uploads/2024/10/cropped-favicon-32x32.png']);
        }

        $defaultContact = [
            'phone' => '0397.999.123',
            'email' => 'trolyluatsu@minhanhvulaw.com',
            'address' => '60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh',
            'working_hours' => '08:00 - 17:30',
            'website' => 'www.minhanhvulaw.com',
            'license' => '79.2026.02.5342/TP/ĐKHĐ',
            'slogan' => 'Quản trị rủi ro pháp lý cùng bạn',
        ];
        $placeholderValues = [
            'phone' => ['(028) 7777 7999', '0901.234.567', '028.7300.7788'],
            'email' => ['sales@matbao.ws', 'info@minhanhvulaw.com'],
            'address' => ['54 đường C1, Phường Tân Bình, TP. Hồ Chí Minh'],
        ];

        $contact = ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'contact'],
            ['setting_value' => $defaultContact, 'updated_at' => now()]
        );
        $rawContact = $contact->getAttribute('setting_value');
        $contactVal = is_array($rawContact) ? $rawContact : [];
        $mergedContact = $contactVal;
        foreach ($defaultContact as $k => $v) {
            $placeholders = $placeholderValues[$k] ?? [];
            if (empty($mergedContact[$k]) || in_array($mergedContact[$k], $placeholders, true)) {
                $mergedContact[$k] = $v;
            }
        }
        if ($mergedContact !== $contactVal) {
            $contact->update(['setting_value' => $mergedContact]);
        }

        $defaultSeo = [
            'title' => 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ | Quản trị rủi ro pháp lý cùng bạn - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'description' => 'Công ty Luật TNHH MTV Minh Anh Vũ (MAV Law) - Quản trị rủi ro pháp lý cùng bạn. Chuyên sâu Tranh tụng, Pháp lý Doanh nghiệp, Dịch vụ giấy phép, Thuế và Di trú.',
        ];
        $seo = ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'seo'],
            ['setting_value' => $defaultSeo, 'updated_at' => now()]
        );
        $rawSeo = $seo->getAttribute('setting_value');
        $seoVal = is_array($rawSeo) ? $rawSeo : [];
        if (in_array($seoVal['title'] ?? '', ['', 'Laravel Ecommerce Core'], true)) {
            $seo->update(['setting_value' => $defaultSeo]);
        }

        $defaultTheme = [
            'primary_color' => '#dc2626',
            'secondary_color' => '#0b1a30',
            'alert_color' => '#b91c1c',
            'dark_color' => '#06101e',
            'layout' => 'default',
        ];
        ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'theme'],
            ['setting_value' => $defaultTheme, 'updated_at' => now()]
        );

        $defaultSocial = [
            'facebook' => '#',
            'youtube' => '#',
            'linkedin' => '#',
            'tiktok' => '#',
        ];
        ProjectSetting::query()->firstOrCreate(
            ['setting_key' => 'social_links'],
            ['setting_value' => $defaultSocial, 'updated_at' => now()]
        );
    }

    /**
     * @return array<string, PostCategory>
     */
    private function seedPostCategories(LocalizedSlugService $slugService): array
    {
        $definitions = [
            [
                'slug' => 'tranh-tung',
                'name' => ['vi' => 'Tranh Tụng', 'en' => 'Litigation'],
                'description' => [
                    'vi' => 'Phân tích góc độ pháp lý, trách nhiệm hình sự, dân sự và thực tiễn tranh tụng tại Tòa án.',
                    'en' => 'Legal analysis and litigation insights.',
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'hanh-chinh-mang',
                'name' => ['vi' => 'Hành Chính & Mạng', 'en' => 'Administrative & Cyber Law'],
                'description' => [
                    'vi' => 'Pháp luật về hành chính và quy định an ninh mạng, thông tin truyền thông.',
                    'en' => 'Administrative and cyber regulations.',
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => 'to-tung-hinh-su',
                'name' => ['vi' => 'Tố Tụng Hình Sự', 'en' => 'Criminal Procedure'],
                'description' => [
                    'vi' => 'Án lệ, nguyên tắc suy đoán vô tội và thu thập chứng cứ hợp pháp trong tố tụng hình sự.',
                    'en' => 'Criminal procedure and case law.',
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => 'hoc-tap-nghien-cuu',
                'name' => ['vi' => 'Học Tập & Nghiên Cứu Pháp Lý', 'en' => 'Legal Research & Insights'],
                'description' => [
                    'vi' => 'Các bài viết phân tích tình huống pháp lý thực tiễn, bình luận án lệ và giải đáp vướng mắc pháp luật thường gặp do đội ngũ Luật sư Minh Anh Vũ trực tiếp nghiên cứu và chia sẻ.',
                    'en' => 'Legal research, case commentary, and FAQ by Minh Anh Vu Law Firm.',
                ],
                'sort_order' => 4,
            ],
        ];

        $result = [];
        foreach ($definitions as $def) {
            $cat = PostCategory::query()->firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                ]
            );
            if ($cat->wasRecentlyCreated || $cat->localizedSlugs()->count() === 0) {
                $slugService->sync($cat, ['vi' => $def['slug'], 'en' => $def['slug']]);
            }
            $result[$def['slug']] = $cat;
        }

        return $result;
    }

    /**
     * @param  array<string, PostCategory>  $categories
     */
    private function seedPosts(array $categories, LocalizedSlugService $slugService): void
    {
        $posts = [
            [
                'slug' => 'chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai',
                'category_slug' => 'tranh-tung',
                'published_at' => Carbon::create(2026, 3, 10, 9, 0, 0),
                'image_url' => '/assets/uploads/2024/10/chay-quan-karaoke.jpg',
                'seo_keys' => 'Kiến thức pháp luật',
                'title' => [
                    'vi' => 'Cháy quán karaoke chết 13 người: Trách nhiệm thuộc về ai?',
                    'en' => 'Cháy quán karaoke chết 13 người: Trách nhiệm thuộc về ai?',
                ],
                'summary' => [
                    'vi' => 'Phân tích góc độ pháp lý và trách nhiệm hình sự trong vụ tai nạn hỏa hoạn nghiêm trọng, trách nhiệm bồi thường thiệt hại theo quy định pháp luật.',
                    'en' => 'Phân tích góc độ pháp lý và trách nhiệm hình sự trong vụ tai nạn hỏa hoạn nghiêm trọng, trách nhiệm bồi thường thiệt hại theo quy định pháp luật.',
                ],
                'content' => [
                    'vi' => '<p class="font-semibold text-lg text-navy-900">Vụ hỏa hoạn nghiêm trọng tại cơ sở kinh doanh dịch vụ giải trí khiến nhiều người thiệt mạng đã đặt ra câu hỏi lớn về trách nhiệm pháp lý của chủ cơ sở, đơn vị thi công và các cơ quan thẩm duyệt PCCC.</p>'
                        .'<h3 class="text-xl font-bold text-navy-900 mt-6 mb-3">1. Trách nhiệm của chủ cơ sở kinh doanh</h3>'
                        .'<p>Theo quy định tại Điều 313 Bộ luật Hình sự về tội "Vi phạm quy định về phòng cháy, chữa cháy", người nào vi phạm quy định về PCCC gây thiệt hại cho tính mạng hoặc gây thiệt hại nghiêm trọng cho sức khỏe, tài sản của người khác có thể bị phạt tù từ 02 năm đến 12 năm.</p>'
                        .'<h3 class="text-xl font-bold text-navy-900 mt-6 mb-3">2. Trách nhiệm bồi thường thiệt hại</h3>'
                        .'<p>Bên cạnh trách nhiệm hình sự, người gây ra thiệt hại còn phải bồi thường toàn bộ chi phí mai táng, viện phí, tổn thất tinh thần và nghĩa vụ cấp dưỡng cho người phụ thuộc theo quy định của Bộ luật Dân sự.</p>',
                    'en' => '<p class="font-semibold text-lg text-navy-900">Vụ hỏa hoạn nghiêm trọng tại cơ sở kinh doanh dịch vụ giải trí khiến nhiều người thiệt mạng đã đặt ra câu hỏi lớn về trách nhiệm pháp lý của chủ cơ sở, đơn vị thi công và các cơ quan thẩm duyệt PCCC.</p>',
                ],
            ],
            [
                'slug' => 'xu-phat-nguoi-quay-dang-clip-doan-xe-chu-tich-quoc-hoi',
                'category_slug' => 'hanh-chinh-mang',
                'published_at' => Carbon::create(2026, 3, 9, 9, 0, 0),
                'image_url' => '/assets/uploads/2024/10/xu-phat_tung_tin.jpg',
                'seo_keys' => 'Nghị định 15/2020',
                'title' => [
                    'vi' => 'Xử phạt người quay, đăng clip ‘đoàn xe Chủ tịch Quốc hội’',
                    'en' => 'Xử phạt người quay, đăng clip ‘đoàn xe Chủ tịch Quốc hội’',
                ],
                'summary' => [
                    'vi' => 'Pháp luật về phát tán thông tin sai lệch trên không gian mạng và các chế tài hành chính, hình sự cần đặc biệt lưu ý khi tham gia mạng xã hội.',
                    'en' => 'Pháp luật về phát tán thông tin sai lệch trên không gian mạng và các chế tài hành chính, hình sự cần đặc biệt lưu ý khi tham gia mạng xã hội.',
                ],
                'content' => [
                    'vi' => '<p class="font-semibold text-lg text-navy-900">Mạng xã hội là không gian giao tiếp rộng mở, tuy nhiên việc tự ý đăng tải video cắt ghép sai sự thật có thể dẫn đến những chế tài xử phạt hành chính nghiêm khắc hoặc truy cứu trách nhiệm hình sự.</p>'
                        .'<h3 class="text-xl font-bold text-navy-900 mt-6 mb-3">1. Quy định về xử phạt vi phạm hành chính</h3>'
                        .'<p>Theo Nghị định 15/2020/NĐ-CP của Chính phủ, hành vi cung cấp, chia sẻ thông tin giả mạo, thông tin sai sự thật, xuyên tạc, vu khống, xúc phạm uy tín của cơ quan, tổ chức sẽ bị phạt tiền từ 10.000.000 đồng đến 20.000.000 đồng đối với tổ chức (cá nhân vi phạm chịu mức phạt bằng 1/2).</p>',
                    'en' => '<p class="font-semibold text-lg text-navy-900">Mạng xã hội là không gian giao tiếp rộng mở, tuy nhiên việc tự ý đăng tải video cắt ghép sai sự thật có thể dẫn đến những chế tài xử phạt hành chính nghiêm khắc hoặc truy cứu trách nhiệm hình sự.</p>',
                ],
            ],
            [
                'slug' => 'chieu-bat-toi-pham-co-mot-khong-hai',
                'category_slug' => 'to-tung-hinh-su',
                'published_at' => Carbon::create(2026, 3, 8, 9, 0, 0),
                'image_url' => '/assets/uploads/2024/10/cong-an-bat-toi-pham.jpg',
                'seo_keys' => 'Án lệ & Thực tiễn',
                'title' => [
                    'vi' => 'Chiêu’ bắt tội phạm ‘có một không hai: Góc nhìn pháp lý',
                    'en' => 'Chiêu’ bắt tội phạm ‘có một không hai: Góc nhìn pháp lý',
                ],
                'summary' => [
                    'vi' => 'Phân tích nghiệp vụ điều tra và nguyên tắc thu thập chứng cứ hợp pháp trong Bộ luật Tố tụng Hình sự để đảm bảo quyền lợi bị can, bị cáo.',
                    'en' => 'Phân tích nghiệp vụ điều tra và nguyên tắc thu thập chứng cứ hợp pháp trong Bộ luật Tố tụng Hình sự để đảm bảo quyền lợi bị can, bị cáo.',
                ],
                'content' => [
                    'vi' => '<p class="font-semibold text-lg text-navy-900">Quá trình đấu tranh phòng chống tội phạm đòi hỏi sự mưu trí, dũng cảm nhưng luôn phải tuân thủ nghiêm ngặt các quy định của Bộ luật Tố tụng Hình sự.</p>'
                        .'<h3 class="text-xl font-bold text-navy-900 mt-6 mb-3">1. Nguyên tắc thu thập chứng cứ hợp pháp</h3>'
                        .'<p>Mọi chứng cứ dùng để chứng minh tội phạm phải được thu thập theo đúng trình tự luật định. Bất kỳ tài liệu, đồ vật nào thu thập trái luật đều không có giá trị chứng cứ để buộc tội.</p>',
                    'en' => '<p class="font-semibold text-lg text-navy-900">Quá trình đấu tranh phòng chống tội phạm đòi hỏi sự mưu trí, dũng cảm nhưng luôn phải tuân thủ nghiêm ngặt các quy định của Bộ luật Tố tụng Hình sự.</p>',
                ],
            ],
        ];

        foreach ($posts as $item) {
            $categoryId = $categories[$item['category_slug']]->id ?? null;
            $post = Post::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $categoryId,
                    'title' => $item['title'],
                    'summary' => $item['summary'],
                    'content' => $item['content'],
                    'image_url' => $item['image_url'],
                    'seo_keys' => $item['seo_keys'],
                    'is_active' => true,
                    'published_at' => $item['published_at'],
                ]
            );
            if ($post->wasRecentlyCreated || $post->localizedSlugs()->count() === 0) {
                $slugService->sync($post, ['vi' => $item['slug'], 'en' => $item['slug']]);
            }
        }
    }

    private function seedPages(LocalizedSlugService $slugService): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $pages = [
            [
                'slug' => 'chinh-sach-bao-mat',
                'title' => ['vi' => 'Chính sách bảo mật thông tin khách hàng', 'en' => 'Privacy Policy'],
                'published_html' => [
                    'vi' => '<div class="max-w-4xl mx-auto px-4 py-16"><h1 class="text-3xl font-bold text-navy-900 mb-6">Chính sách bảo mật thông tin khách hàng</h1><p class="text-slate-600 leading-relaxed">Công ty Luật TNHH MTV Minh Anh Vũ cam kết bảo mật tuyệt đối mọi thông tin, hồ sơ và tài liệu pháp lý của khách hàng theo quy định của Luật Luật sư và Bộ Quy tắc Đạo đức và Ứng xử nghề nghiệp Luật sư Việt Nam.</p></div>',
                    'en' => '<div class="max-w-4xl mx-auto px-4 py-16"><h1 class="text-3xl font-bold text-navy-900 mb-6">Privacy Policy</h1><p class="text-slate-600 leading-relaxed">Minh Anh Vu Law Firm commits to absolute confidentiality of client information and legal documents.</p></div>',
                ],
                'meta_title' => ['vi' => 'Chính sách bảo mật - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ', 'en' => 'Privacy Policy - Minh Anh Vu Law'],
                'meta_description' => ['vi' => 'Cam kết bảo mật tuyệt đối thông tin khách hàng tại Công ty Luật TNHH MTV Minh Anh Vũ.', 'en' => 'Client confidentiality policy at Minh Anh Vu Law.'],
            ],
        ];

        foreach ($pages as $item) {
            $page = Page::query()->withTrashed()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'title' => $item['title'],
                    'published_html' => $item['published_html'],
                    'meta_title' => $item['meta_title'],
                    'meta_description' => $item['meta_description'],
                    'is_active' => true,
                    'published_at' => Carbon::create(2026, 3, 1, 8, 0, 0),
                ]
            );
            if ($page->trashed()) {
                $page->restore();
            }
            if ($page->wasRecentlyCreated || $page->localizedSlugs()->count() === 0) {
                $slugService->sync($page, ['vi' => $item['slug'], 'en' => $item['slug']]);
            }
        }
    }

    private function seedPartners(): void
    {
        if (! Schema::hasTable('partner_groups') || ! Schema::hasTable('partners')) {
            return;
        }

        $group = PartnerGroup::query()->firstOrCreate(
            ['key' => 'trusted'],
            [
                'name' => 'Đối tác tin cậy',
                'sort_order' => 1,
            ]
        );

        for ($i = 1; $i <= 6; $i++) {
            $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Partner::query()->firstOrCreate(
                [
                    'partner_group_id' => $group->id,
                    'name' => 'Đối tác '.$i,
                ],
                [
                    'logo_path' => "/assets/uploads/2024/10/logo-{$num}.png",
                    'url' => null,
                    'sort_order' => $i,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedMenus(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        $menu = Menu::query()->firstOrCreate(
            ['key' => 'primary'],
            [
                'name' => 'Menu chính',
                'is_active' => true,
            ]
        );

        // Remove default core placeholder items if none of MAV's 7 items exist yet
        $hasMavItems = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('url', '/tranh-tung-va-tu-van/')
            ->exists();

        if (! $hasMavItems) {
            MenuItem::query()->where('menu_id', $menu->id)->delete();
        }

        $tree = [
            [
                'url' => '/gioi-thieu/',
                'label' => ['vi' => 'Giới thiệu', 'en' => 'About Us'],
                'sort_order' => 1,
                'children' => [
                    ['url' => '/gioi-thieu/', 'label' => ['vi' => 'Giới thiệu chung MAV', 'en' => 'Overview'], 'sort_order' => 1],
                    ['url' => '/gioi-thieu/#tam-nhin-su-menh', 'label' => ['vi' => 'Tầm nhìn & Sứ mệnh', 'en' => 'Vision & Mission'], 'sort_order' => 2],
                    ['url' => '/gioi-thieu/#gia-tri-cot-loi', 'label' => ['vi' => 'Giá trị cốt lõi', 'en' => 'Core Values'], 'sort_order' => 3],
                ],
            ],
            [
                'url' => '/tranh-tung-va-tu-van/',
                'label' => ['vi' => 'Tranh tụng & Tư vấn', 'en' => 'Litigation & Consulting'],
                'sort_order' => 2,
                'children' => [
                    ['url' => '/tranh-tung-va-tu-van/#tranh-chap-cong-ty', 'label' => ['vi' => 'Tranh Chấp Công Ty', 'en' => 'Corporate Disputes'], 'sort_order' => 1],
                    ['url' => '/tranh-tung-va-tu-van/#tranh-chap-hop-dong', 'label' => ['vi' => 'Tranh Chấp Hợp Đồng Dân Sự & Thương Mại', 'en' => 'Contract Disputes'], 'sort_order' => 2],
                    ['url' => '/tranh-tung-va-tu-van/#thua-ke-di-chuc', 'label' => ['vi' => 'Tranh Chấp Quyền Thừa Kế, Di Chúc', 'en' => 'Inheritance Disputes'], 'sort_order' => 3],
                    ['url' => '/tranh-tung-va-tu-van/#tranh-chap-lao-dong', 'label' => ['vi' => 'Tranh chấp lao động', 'en' => 'Labor Disputes'], 'sort_order' => 4],
                    ['url' => '/tranh-tung-va-tu-van/#tranh-chap-hang-hai', 'label' => ['vi' => 'Tranh chấp hàng hải', 'en' => 'Maritime Disputes'], 'sort_order' => 5],
                ],
            ],
            [
                'url' => '/doanh-nghiep/',
                'label' => ['vi' => 'Doanh Nghiệp', 'en' => 'Corporate Law'],
                'sort_order' => 3,
                'children' => [
                    ['url' => '/doanh-nghiep/#phap-che-thue-ngoai', 'label' => ['vi' => 'Phòng pháp chế thuê ngoài', 'en' => 'Outsourced Legal Department'], 'sort_order' => 1],
                    ['url' => '/doanh-nghiep/#tu-van-hang-thang', 'label' => ['vi' => 'Tư vấn pháp luật hàng tháng', 'en' => 'Monthly Legal Retainer'], 'sort_order' => 2],
                    ['url' => '/doanh-nghiep/#legal-health-check', 'label' => ['vi' => 'Kiểm tra pháp lý toàn diện “Legal Health Check”', 'en' => 'Legal Health Check'], 'sort_order' => 3],
                    ['url' => '/doanh-nghiep/#huan-luyen-dao-tao', 'label' => ['vi' => 'Huấn luyện Đào tạo pháp luật', 'en' => 'In-house Legal Training'], 'sort_order' => 4],
                ],
            ],
            [
                'url' => '/dich-vu/',
                'label' => ['vi' => 'Dịch Vụ', 'en' => 'Services'],
                'sort_order' => 4,
                'children' => [
                    ['url' => '/dich-vu/#thanh-lap-cong-ty', 'label' => ['vi' => 'Thành lập công ty', 'en' => 'Company Formation'], 'sort_order' => 1],
                    ['url' => '/dich-vu/#dang-ky-ho-kinh-doanh', 'label' => ['vi' => 'Đăng ký hộ kinh doanh', 'en' => 'Household Business Registration'], 'sort_order' => 2],
                    ['url' => '/dich-vu/#tam-dung-kinh-doanh', 'label' => ['vi' => 'Tạm dừng kinh doanh', 'en' => 'Business Suspension'], 'sort_order' => 3],
                    ['url' => '/dich-vu/#giai-the-cong-ty', 'label' => ['vi' => 'Giải thể công ty', 'en' => 'Company Dissolution'], 'sort_order' => 4],
                    ['url' => '/dich-vu/#pha-san-cong-ty', 'label' => ['vi' => 'Phá sản công ty', 'en' => 'Company Bankruptcy'], 'sort_order' => 5],
                    ['url' => '/dich-vu/#giay-phep-lao-dong', 'label' => ['vi' => 'Giấy phép lao động', 'en' => 'Work Permit'], 'sort_order' => 6],
                    ['url' => '/dich-vu/#dich-vu-visa', 'label' => ['vi' => 'Dịch vụ visa', 'en' => 'Visa Services'], 'sort_order' => 7],
                    ['url' => '/dich-vu/#the-tam-tru', 'label' => ['vi' => 'Thẻ tạm trú', 'en' => 'Temporary Residence Card'], 'sort_order' => 8],
                    ['url' => '/dich-vu/#the-thuong-tru', 'label' => ['vi' => 'Thẻ thường trú', 'en' => 'Permanent Residence Card'], 'sort_order' => 9],
                    ['url' => '/dich-vu/#dich-vu-di-tru', 'label' => ['vi' => 'Dịch vụ di trú', 'en' => 'Immigration Services'], 'sort_order' => 10],
                    ['url' => '/dich-vu/#ly-lich-tu-phap', 'label' => ['vi' => 'Lý lịch tư pháp', 'en' => 'Judicial Record'], 'sort_order' => 11],
                    ['url' => '/dich-vu/#luat-su-ca-nhan', 'label' => ['vi' => 'Luật sư cá nhân', 'en' => 'Personal Lawyer'], 'sort_order' => 12],
                    ['url' => '/dich-vu/#bao-cao-tai-chinh', 'label' => ['vi' => 'Báo cáo tài chính', 'en' => 'Financial Statements'], 'sort_order' => 13],
                    ['url' => '/dich-vu/#bao-cao-thue', 'label' => ['vi' => 'Báo cáo thuế', 'en' => 'Tax Reporting'], 'sort_order' => 14],
                    ['url' => '/dich-vu/#hoan-thue-tncn', 'label' => ['vi' => 'Hoàn thuế thu nhập cá nhân', 'en' => 'PIT Refund'], 'sort_order' => 15],
                    ['url' => '/dich-vu/#linh-vuc-hanh-chinh', 'label' => ['vi' => 'Lĩnh vực Hành chính', 'en' => 'Administrative Procedures'], 'sort_order' => 16],
                    ['url' => '/dich-vu/#khai-sinh-ho-tich', 'label' => ['vi' => 'Làm Giấy Khai sinh - Hộ tịch', 'en' => 'Civil Registry & Birth Certificate'], 'sort_order' => 17],
                ],
            ],
            [
                'url' => '/hop-tac-kinh-doanh/',
                'label' => ['vi' => 'Hợp tác kinh doanh', 'en' => 'Business Cooperation'],
                'sort_order' => 5,
                'children' => [],
            ],
            [
                'url' => '/hoc-tap-nghien-cuu/',
                'label' => ['vi' => 'Học tập Nghiên cứu', 'en' => 'Legal Research'],
                'sort_order' => 6,
                'children' => [],
            ],
            [
                'url' => '/lien-he/',
                'label' => ['vi' => 'Liên hệ Luật sư', 'en' => 'Contact Lawyer'],
                'sort_order' => 7,
                'children' => [],
            ],
        ];

        foreach ($tree as $branch) {
            $parent = MenuItem::query()->firstOrCreate(
                [
                    'menu_id' => $menu->id,
                    'parent_id' => null,
                    'url' => $branch['url'],
                ],
                [
                    'label' => $branch['label'],
                    'type' => MenuItem::TYPE_URL,
                    'sort_order' => $branch['sort_order'],
                    'is_active' => true,
                    'target_blank' => false,
                ]
            );

            foreach ($branch['children'] as $child) {
                MenuItem::query()->firstOrCreate(
                    [
                        'menu_id' => $menu->id,
                        'parent_id' => $parent->id,
                        'url' => $child['url'],
                        'sort_order' => $child['sort_order'],
                    ],
                    [
                        'label' => $child['label'],
                        'type' => MenuItem::TYPE_URL,
                        'is_active' => true,
                        'target_blank' => false,
                    ]
                );
            }
        }
    }
}
