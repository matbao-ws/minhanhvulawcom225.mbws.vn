<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Theme\MinhAnhVuThemeSeeder;

require_once __DIR__.'/../../theme/MinhAnhVuThemeSeeder.php';

class MinhAnhVuThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_seeder_is_idempotent_and_populates_all_required_data(): void
    {
        (new MinhAnhVuThemeSeeder)->run();
        $firstPostCount = Post::query()->count();
        $firstCategoryCount = PostCategory::query()->count();
        $firstPartnerCount = Partner::query()->count();
        $firstMenuCount = Menu::query()->count();
        $firstMenuItemCount = MenuItem::query()->count();
        $firstPageCount = Page::query()->count();

        // Run a second time to verify idempotency
        (new MinhAnhVuThemeSeeder)->run();

        $this->assertSame(3, $firstPostCount);
        $this->assertSame($firstPostCount, Post::query()->count());
        $this->assertSame(4, $firstCategoryCount);
        $this->assertSame($firstCategoryCount, PostCategory::query()->count());
        $this->assertSame(6, $firstPartnerCount);
        $this->assertSame($firstPartnerCount, Partner::query()->count());
        $this->assertSame(1, $firstMenuCount);
        $this->assertSame($firstMenuCount, Menu::query()->count());
        $this->assertSame($firstMenuItemCount, MenuItem::query()->count());
        $this->assertSame($firstPageCount, Page::query()->count());
    }

    public function test_all_storefront_pages_render_successfully_with_approved_client_info(): void
    {
        (new MinhAnhVuThemeSeeder)->run();

        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('CÔNG TY LUẬT TNHH MTV MINH ANH VŨ');
        $home->assertSee('Quản trị rủi ro pháp lý cùng bạn');
        $home->assertSee('0397.999.123');
        $home->assertSee('tel:0397999123');
        $home->assertSee('trolyluatsu@minhanhvulaw.com');
        $home->assertSee('60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh');
        $home->assertSee('79.2026.02.5342/TP/ĐKHĐ');

        $routes = [
            '/gioi-thieu' => 'TẦM NHÌN & SỨ MỆNH',
            '/tranh-tung-va-tu-van' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-su-tranh-tung' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-dan-su' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-hinh-su' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-hon-nhan-gia-dinh' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-lao-dong' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/luat-nha-dat' => '1. Tranh Chấp Công Ty & Cổ Đông',
            '/doanh-nghiep' => '1. Phòng Pháp Chế Thuê Ngoài Trọn Gói',
            '/phong-phap-che-thue-ngoai' => '1. Phòng Pháp Chế Thuê Ngoài Trọn Gói',
            '/tu-van-phap-ly-thuong-xuyen' => '2. Tư Vấn Pháp Luật Hàng Tháng',
            '/dao-tao-in-house' => '4. Huấn Luyện & Đào Tạo Pháp Luật Nội Bộ',
            '/luat-doanh-nghiep' => '1. Phòng Pháp Chế Thuê Ngoài Trọn Gói',
            '/dich-vu' => 'Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm',
            '/thu-tuc-giay-phep' => 'Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm',
            '/luat-su-rieng' => 'Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm',
            '/luat-ve-thue' => 'Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm',
            '/category/dich-vu' => 'Danh Mục 17 Dịch Vụ Pháp Lý Trọng Tâm',
            '/hop-tac-kinh-doanh' => 'LỜI TRI ÂN TỪ MINH ANH VŨ',
            '/hoc-tap-nghien-cuu' => 'Kiến Thức Pháp Luật, Án Lệ & Hỏi Đáp',
            '/tin-tuc' => 'Kiến Thức Pháp Luật, Án Lệ & Hỏi Đáp',
            '/category/tin-tuc' => 'Kiến Thức Pháp Luật, Án Lệ & Hỏi Đáp',
            '/lien-he' => 'Gửi Yêu Cầu Cho Luật Sư',
            '/luat-su' => 'Luật sư Phạm Thị Thanh Thảo',
            '/tuyen-dung' => 'THÔNG BÁO TUYỂN DỤNG & TIẾP NHẬN THỰC TẬP',
            '/tag/luat-gia-dinh' => 'Tư vấn Hôn nhân & Gia đình',
            '/tag/luat-hinh-su' => 'Luật sư Bào chữa & Tố tụng Hình sự',
            '/chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai' => 'Cháy quán karaoke chết 13 người: Trách nhiệm thuộc về ai?',
            '/xu-phat-nguoi-quay-dang-clip-doan-xe-chu-tich-quoc-hoi' => 'Xử phạt người quay, đăng clip ‘đoàn xe Chủ tịch Quốc hội’',
            '/chieu-bat-toi-pham-co-mot-khong-hai' => 'Chiêu’ bắt tội phạm ‘có một không hai: Góc nhìn pháp lý',
            '/bai-viet/chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai' => 'Cháy quán karaoke chết 13 người: Trách nhiệm thuộc về ai?',
            '/trang/chinh-sach-bao-mat' => 'Chính sách bảo mật thông tin khách hàng',
        ];

        foreach ($routes as $uri => $expectedText) {
            $response = $this->get($uri);
            $this->assertSame(200, $response->getStatusCode(), "Failed 200 status on URI: {$uri}");
            $response->assertSee($expectedText);
        }
    }

    public function test_consultation_form_saves_contact_submission(): void
    {
        (new MinhAnhVuThemeSeeder)->run();

        $response = $this->postJson('/lien-he', [
            'fullname' => 'Nguyễn Văn Khách',
            'phone' => '0901234567',
            'email' => 'khach@example.com',
            'service' => 'Tranh tụng & Tư vấn giải quyết tranh chấp',
            'message' => 'Cần tư vấn tranh chấp hợp đồng thương mại.',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Nguyễn Văn Khách',
            'phone' => '0901234567',
            'email' => 'khach@example.com',
        ]);
        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_inline_editor_hooks_render_for_authenticated_superadmin(): void
    {
        (new MinhAnhVuThemeSeeder)->run();

        $role = Role::query()->firstOrCreate(
            ['slug' => 'superadmin'],
            ['name' => 'Super Admin', 'is_superadmin' => true]
        );
        $admin = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($admin)->get('/?edit=1');
        $response->assertOk();
        $response->assertSee('data-section-list="home.sections"', false);
        $response->assertSee('data-list-key="home.hero.slides"', false);
        $response->assertSee('data-block-key="partials.header.slogan"', false);
    }
}
