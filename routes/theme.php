<?php

use App\Http\Controllers\Client\PageController;
use App\Models\ContactSubmission;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\ProjectSetting;
use App\Models\Redirect;
use App\Services\LocalizedContent;
use App\Services\LocalizedSlugService;
use App\Services\MenuService;
use App\Services\Seo\RedirectService;
use App\Services\Seo\SeoMetaFactory;
use App\Services\SiteBranding;
use App\Services\SiteListService;
use App\Support\ClientEditorChrome;
use App\Support\FeatureGate;
use App\Support\SearchTerm;
use App\Support\SectionRegistry;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Theme\MinhAnhVuThemeSeeder;

require_once dirname(__DIR__).'/theme/MinhAnhVuThemeSeeder.php';

$ensureThemeSeeded = static function (): void {
    if (app()->runningUnitTests()) {
        return;
    }

    if (app()->bound('theme.mav.seeded_checked')) {
        return;
    }
    app()->instance('theme.mav.seeded_checked', true);

    try {
        if (! Schema::hasTable('posts') || ! Schema::hasTable('project_settings') || ! Schema::hasTable('menus')) {
            return;
        }

        $hasMavPost = Post::query()
            ->where('slug', 'chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai')
            ->exists();

        if (! $hasMavPost) {
            (new MinhAnhVuThemeSeeder)->run();
        }
    } catch (Throwable) {
    }
};

if (! app()->bound('theme.composers.registered')) {
    app()->instance('theme.composers.registered', true);

    app()->bind(SectionRegistry::class, static fn () => new SectionRegistry([
        'hero' => [
            'label' => 'Banner trình chiếu (Hero Slider)',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'about-overview' => [
            'label' => 'Về Minh Anh Vũ (Giới thiệu tổng quan)',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'why-choose-us' => [
            'label' => 'Vì sao chọn Minh Anh Vũ (5 cam kết)',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'practice-areas' => [
            'label' => 'Lĩnh vực hoạt động chính (3 mảng lớn)',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'latest-news' => [
            'label' => 'Tin tức mới nhất',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'consultation-form' => [
            'label' => 'Form đăng ký tư vấn pháp lý',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
        'partners' => [
            'label' => 'Đối tác tin cậy',
            'variants' => [],
            'settings' => ['background', 'alignment', 'spacing', 'accent'],
        ],
    ]));

    View::composer(['client.catalog.category', 'client.blog.category'], function ($view): void {
        $data = $view->getData();
        $items = $data['products'] ?? $data['posts'] ?? null;
        if (
            $items instanceof Paginator
            && $items->isNotEmpty()
            && Schema::hasTable('menus')
            && ! Menu::query()->exists()
        ) {
            request()->query->set(ClientEditorChrome::FLAG, ClientEditorChrome::OFF);
        }
    });

    View::composer('client.*', function ($view) use ($ensureThemeSeeded): void {
        $ensureThemeSeeded();
        $locale = app()->getLocale();
        $cacheKey = 'theme.shared_data.'.$locale;

        if (! app()->bound($cacheKey)) {
            $featuredPosts = collect();
            $recentPosts = collect();
            $trustedPartners = collect();
            $socialLinks = [
                'facebook' => '#',
                'youtube' => '#',
                'linkedin' => '#',
                'tiktok' => '#',
            ];
            $contactDetails = [
                'phone' => '0397.999.123',
                'email' => 'trolyluatsu@minhanhvulaw.com',
                'address' => '60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh',
                'working_hours' => '08:00 - 17:30',
                'website' => 'www.minhanhvulaw.com',
                'license' => '79.2026.02.5342/TP/ĐKHĐ',
                'slogan' => 'Quản trị rủi ro pháp lý cùng bạn',
            ];

            try {
                if (Schema::hasTable('posts')) {
                    $featuredPosts = Post::query()
                        ->where('is_active', true)
                        ->with(['category', 'localizedSlugs'])
                        ->latest('published_at')
                        ->limit(3)
                        ->get();

                    $recentPosts = Post::query()
                        ->where('is_active', true)
                        ->with(['category', 'localizedSlugs'])
                        ->latest('published_at')
                        ->limit(5)
                        ->get();
                }

                if (Schema::hasTable('partner_groups') && Schema::hasTable('partners')) {
                    $group = PartnerGroup::query()->where('key', 'trusted')->first();
                    if ($group instanceof PartnerGroup) {
                        $trustedPartners = Partner::query()
                            ->where('partner_group_id', $group->id)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->get();
                    }
                }

                if (Schema::hasTable('project_settings')) {
                    $settingsMap = ProjectSetting::query()
                        ->whereIn('setting_key', ['social_links', 'contact'])
                        ->pluck('setting_value', 'setting_key');

                    $socialVal = $settingsMap->get('social_links');
                    if (is_array($socialVal)) {
                        $socialLinks = array_merge($socialLinks, array_filter($socialVal, 'is_string'));
                    }

                    $contactVal = $settingsMap->get('contact');
                    if (is_array($contactVal)) {
                        $contactDetails = array_merge($contactDetails, array_filter($contactVal, 'is_string'));
                    }
                }
            } catch (Throwable) {
            }

            $branding = app(SiteBranding::class)->current();
            $branding['contact'] = array_merge($contactDetails, is_array($branding['contact'] ?? null) ? array_filter($branding['contact'], 'is_string') : []);
            if (empty($branding['name']) || in_array($branding['name'], ['Laravel Ecommerce Core', 'Minh Anh Vũ Law'], true)) {
                $branding['name'] = 'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ';
            }

            app()->instance($cacheKey, [
                'content' => app(LocalizedContent::class),
                'lists' => app(SiteListService::class),
                'menuService' => app(MenuService::class),
                'siteBranding' => $branding,
                'socialLinks' => $socialLinks,
                'featuredPosts' => $featuredPosts,
                'recentPosts' => $recentPosts,
                'trustedPartners' => $trustedPartners,
            ]);
        }

        /** @var array<string, mixed> $shared */
        $shared = app($cacheKey);

        foreach ($shared as $key => $value) {
            if (! array_key_exists($key, $view->getData())) {
                $view->with($key, $value);
            }
        }
    });
}

$homeAction = function (SeoMetaFactory $seo, SiteBranding $branding) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    if (! config('multilingual.hide_default_locale', true) && request()->path() === '/') {
        return redirect('/admin');
    }

    if (! Schema::hasTable('posts') || (! Post::query()->exists() && ! MenuItem::query()->exists())) {
        return redirect('/admin');
    }

    return view('client.pages.home', [
        'activeNav' => 'home',
        'seo' => $seo->fallback(
            'CÔNG TY LUẬT TNHH MTV MINH ANH VŨ | Quản trị rủi ro pháp lý cùng bạn - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Công ty Luật TNHH MTV Minh Anh Vũ (MAV Law) - Quản trị rủi ro pháp lý cùng bạn. Chuyên sâu Tranh tụng, Pháp lý Doanh nghiệp, Dịch vụ giấy phép, Thuế và Di trú.',
        ),
    ]);
};

Route::get('/', $homeAction)->name('home');

if (! app()->bound('theme.root_home.registered')) {
    app()->instance('theme.root_home.registered', true);
    app()->booted(function () use ($homeAction): void {
        Route::middleware(['web', 'useDefaultLocale', 'setLocale'])
            ->get('/', $homeAction)
            ->name('root.client.home');

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'client.')) {
                $route->where('locale', '[a-z]{2}|[a-su-z][a-z]{2}|t[b-z][a-z]|ta[a-fh-z]');
            }
        }

        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    });
}

Route::get('gioi-thieu', function (Request $request, SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    if (Schema::hasTable('redirects') && Redirect::query()->where('source_path', '/gioi-thieu')->where('is_active', true)->exists()) {
        return app(RedirectService::class)->handleNotFound($request);
    }

    $ensureThemeSeeded();

    return view('client.pages.about', [
        'activeNav' => 'gioi-thieu',
        'title' => 'Giới thiệu về Công ty Luật Minh Anh Vũ',
        'seo' => $seo->fallback(
            'Giới thiệu chung về Công ty Luật Minh Anh Vũ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Công ty Luật TNHH MTV Minh Anh Vũ (MAV Law) - Quản trị rủi ro pháp lý cùng bạn. Tầm nhìn sứ mệnh và các giá trị cốt lõi.',
        ),
    ]);
})->name('about');

$litigationAction = function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.litigation', [
        'activeNav' => 'tranh-tung-va-tu-van',
        'title' => 'Tranh Tụng & Tư Vấn Giải Quyết Tranh Chấp',
        'seo' => $seo->fallback(
            'Tranh Tụng & Tư Vấn Giải Quyết Tranh Chấp | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Dịch vụ Luật sư Tranh tụng và Tư vấn giải quyết tranh chấp: Tranh chấp công ty, hợp đồng dân sự & thương mại, thừa kế di chúc, lao động, hàng hải.',
        ),
    ]);
};

Route::get('tranh-tung-va-tu-van', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation');
Route::get('luat-su-tranh-tung', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.alias');
Route::get('luat-dan-su', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.civil');
Route::get('luat-hinh-su', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.criminal');
Route::get('luat-hon-nhan-gia-dinh', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.family');
Route::get('luat-lao-dong', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.labor');
Route::get('luat-nha-dat', $litigationAction)->where('locale', '^(?!tag$)[a-z]{2,3}$')->name('litigation.land');

$enterpriseAction = function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.enterprise', [
        'activeNav' => 'doanh-nghiep',
        'title' => 'Dịch Vụ Pháp Lý Doanh Nghiệp',
        'seo' => $seo->fallback(
            'Pháp Lý Doanh Nghiệp | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Dịch vụ pháp lý doanh nghiệp chuyên nghiệp: Phòng pháp chế thuê ngoài, Tư vấn pháp luật hàng tháng, Kiểm tra pháp lý Legal Health Check, Huấn luyện Đào tạo pháp luật.',
        ),
    ]);
};

Route::get('doanh-nghiep', $enterpriseAction)->name('enterprise');
Route::get('phong-phap-che-thue-ngoai', $enterpriseAction)->name('enterprise.outsource');
Route::get('tu-van-phap-ly-thuong-xuyen', $enterpriseAction)->name('enterprise.retainer');
Route::get('dao-tao-in-house', $enterpriseAction)->name('enterprise.training');
Route::get('luat-doanh-nghiep', $enterpriseAction)->name('enterprise.legacy');

$servicesAction = function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.services', [
        'activeNav' => 'dich-vu',
        'title' => 'Hệ Thống Dịch Vụ Pháp Lý Trọng Tâm',
        'seo' => $seo->fallback(
            'Danh Mục Dịch Vụ Pháp Lý | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Hệ thống 17 dịch vụ pháp lý chi tiết: Thành lập công ty, Hộ kinh doanh, Giấy phép lao động, Visa, Thẻ tạm trú, Thẻ thường trú, Di trú, Thuế & Báo cáo tài chính, Luật sư cá nhân.',
        ),
    ]);
};

Route::get('dich-vu', $servicesAction)->name('services');
Route::get('thu-tuc-giay-phep', $servicesAction)->name('services.license');
Route::get('luat-su-rieng', $servicesAction)->name('services.personal');
Route::get('luat-ve-thue', $servicesAction)->name('services.tax');
Route::get('category/dich-vu', $servicesAction)->name('services.category');

Route::get('hop-tac-kinh-doanh', function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.cooperation', [
        'activeNav' => 'hop-tac-kinh-doanh',
        'title' => 'Hợp Tác Kinh Doanh Cùng Minh Anh Vũ',
        'seo' => $seo->fallback(
            'Hợp Tác Kinh Doanh Cùng Công Ty Luật Minh Anh Vũ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Chính sách hợp tác kinh doanh, hợp lực chuyên môn và đồng hành dài hạn cùng Công ty Luật TNHH MTV Minh Anh Vũ.',
        ),
    ]);
})->name('cooperation');

$researchAction = function (Request $request, string $locale, SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    $rawKeyword = $request->query('s') ?? $request->query('q');
    $keyword = is_string($rawKeyword) ? SearchTerm::from($rawKeyword) : null;

    $query = Post::query()
        ->where('is_active', true)
        ->with(['category', 'localizedSlugs'])
        ->latest('published_at');

    if ($keyword !== null) {
        $pattern = SearchTerm::contains($keyword);
        $query->where(function ($sub) use ($pattern, $locale): void {
            $sub->where("title->{$locale}", 'like', $pattern)
                ->orWhere("summary->{$locale}", 'like', $pattern)
                ->orWhere("content->{$locale}", 'like', $pattern);
        });
    }

    $posts = $query->paginate(12)->withQueryString();
    $category = PostCategory::query()->where('slug', 'hoc-tap-nghien-cuu')->first();

    return view('client.blog.category', [
        'activeNav' => 'hoc-tap-nghien-cuu',
        'category' => $category,
        'title' => 'Học Tập & Nghiên Cứu Pháp Lý',
        'searchKeyword' => $keyword,
        'posts' => $posts,
        'seo' => $seo->fallback(
            'Học Tập & Nghiên Cứu Pháp Lý | MAV Law - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Chuyên mục học tập, nghiên cứu pháp luật, án lệ và giải đáp vướng mắc pháp lý từ chuyên gia Luật Minh Anh Vũ.',
        ),
    ]);
};

Route::get('hoc-tap-nghien-cuu', $researchAction)->name('research');
Route::get('tin-tuc', $researchAction)->name('news');
Route::get('category/tin-tuc', $researchAction)->name('news.category');

Route::get('lien-he', function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.contact', [
        'activeNav' => 'lien-he',
        'title' => 'Liên Hệ Luật Sư Tư Vấn',
        'seo' => $seo->fallback(
            'Liên Hệ Luật Sư Tư Vấn | CÔNG TY LUẬT MINH ANH VŨ - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Liên hệ tư vấn pháp lý với Công ty Luật TNHH MTV Minh Anh Vũ. Trụ sở chính: 60/2 Đường số 2 Phường Hiệp Bình, Thành phố Hồ Chí Minh. Hotline 24/7: 0397.999.123.',
        ),
    ]);
})->name('contact');

Route::post('lien-he', function (Request $request) {
    $name = trim((string) ($request->input('name') ?? $request->input('fullname') ?? ''));
    $phone = trim((string) ($request->input('phone') ?? ''));
    $email = trim((string) ($request->input('email') ?? ''));
    if ($email === '') {
        $email = 'khachhang@minhanhvulaw.com';
    }
    $service = trim((string) ($request->input('service') ?? ''));
    $rawMessage = trim((string) ($request->input('message') ?? ''));
    $message = $service !== '' ? "[{$service}] {$rawMessage}" : ($rawMessage !== '' ? $rawMessage : 'Đăng ký tư vấn pháp lý');

    $request->merge([
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'message' => $message,
    ]);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'phone' => ['required', 'string', 'max:40'],
        'email' => ['required', 'email', 'max:150'],
        'message' => ['required', 'string', 'max:3000'],
    ]);

    ContactSubmission::query()->create([
        'name' => $validated['name'],
        'phone' => $validated['phone'],
        'email' => $validated['email'],
        'message' => $validated['message'],
        'ip' => $request->ip(),
        'is_read' => false,
        'is_spam' => false,
    ]);

    $successMsg = 'Luật sư của Công ty Luật Minh Anh Vũ sẽ liên hệ với bạn qua số điện thoại đã cung cấp trong vòng 30 phút.';

    if ($request->expectsJson() || $request->ajax()) {
        return response()->json([
            'success' => true,
            'message' => $successMsg,
        ]);
    }

    return back()->with('contact_success', $successMsg);
})->middleware('throttle:public-contact')->name('contact.submit');

Route::get('luat-su', function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.lawyers', [
        'activeNav' => 'gioi-thieu',
        'title' => 'Đội ngũ Luật sư & Cố vấn',
        'seo' => $seo->fallback(
            'Đội ngũ Luật sư & Cố vấn cấp cao - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Đội ngũ luật sư thành viên và cố vấn cấp cao giàu kinh nghiệm thực tế tại Công ty Luật TNHH MTV Minh Anh Vũ.',
        ),
    ]);
})->name('lawyers');

Route::get('tuyen-dung', function (SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    return view('client.pages.recruitment', [
        'activeNav' => '',
        'title' => 'Tuyển Dụng & Đào Tạo',
        'seo' => $seo->fallback(
            'Tuyển Dụng & Đào Tạo - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            'Thông báo tuyển dụng luật sư thành viên, chuyên viên pháp lý và tiếp nhận thực tập sinh tại Công ty Luật TNHH MTV Minh Anh Vũ.',
        ),
    ]);
})->name('recruitment');

Route::get('tag/{tagSlug}', function (string $locale, string $tagSlug, SeoMetaFactory $seo) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();

    $tags = [
        'luat-gia-dinh' => [
            'title' => 'Chủ đề: Hôn nhân & Gia đình',
            'breadcrumb' => 'Hôn nhân & Gia đình',
            'description' => 'Hỏi đáp pháp luật hôn nhân gia đình.',
            'heading' => 'Tư vấn Hôn nhân & Gia đình',
            'body' => 'MAV Law đồng hành giải quyết các vụ việc ly hôn, phân chia tài sản và thỏa thuận nuôi con trong khuôn khổ dịch vụ Luật sư Tranh tụng và Luật sư riêng.',
        ],
        'luat-hinh-su' => [
            'title' => 'Chủ đề: Hình sự & Bào chữa',
            'breadcrumb' => 'Hình sự & Bào chữa',
            'description' => 'Hỏi đáp pháp luật hình sự.',
            'heading' => 'Luật sư Bào chữa & Tố tụng Hình sự',
            'body' => 'Đội ngũ luật sư và cố vấn cấp cao MAV Law trực tiếp tham gia bào chữa và bảo vệ quyền lợi đương sự trong các vụ án hình sự phức tạp.',
        ],
    ];

    abort_unless(isset($tags[$tagSlug]), 404);
    $tagData = $tags[$tagSlug];

    return view('client.pages.tag', [
        'activeNav' => 'hoc-tap-nghien-cuu',
        'tagSlug' => $tagSlug,
        'tagData' => $tagData,
        'title' => $tagData['title'],
        'seo' => $seo->fallback(
            $tagData['title'].' - CÔNG TY LUẬT TNHH MTV MINH ANH VŨ',
            $tagData['description'],
        ),
    ]);
})->where('tagSlug', '[A-Za-z0-9\-_]+')->name('tag');

$postDetailAction = function (
    string $locale,
    string $slug,
    LocalizedSlugService $localizedSlugs,
    LocalizedContent $content,
    FeatureGate $features,
    SeoMetaFactory $seo
) use ($ensureThemeSeeded) {
    $ensureThemeSeeded();
    abort_unless($features->enabled('cms_page'), 404);

    $post = $localizedSlugs->find(Post::class, $slug, app()->getLocale())
        ?? Post::query()->where('slug', $slug)->first();

    abort_unless($post instanceof Post && $post->is_active, 404);

    $post->load(['category', 'localizedSlugs']);

    $title = $content->get($post, 'title');
    $relatedPosts = Post::query()
        ->where('is_active', true)
        ->whereKeyNot($post->id)
        ->with(['category', 'localizedSlugs'])
        ->latest('published_at')
        ->limit(3)
        ->get();

    return view('client.blog.show', [
        'activeNav' => 'hoc-tap-nghien-cuu',
        'post' => $post,
        'title' => $title,
        'relatedPosts' => $relatedPosts,
        'seo' => $seo->forPost($post, 'client.posts.show'),
    ]);
};

Route::get('bai-viet/{slug}', $postDetailAction)->where('slug', '[A-Za-z0-9\-_]+')->name('posts.show');

foreach ([
    'chay-quan-karaoke-chet-13-nguoi-trach-nhiem-thuoc-ve-ai',
    'xu-phat-nguoi-quay-dang-clip-doan-xe-chu-tich-quoc-hoi',
    'chieu-bat-toi-pham-co-mot-khong-hai',
] as $idx => $articleSlug) {
    Route::get($articleSlug, function (
        string $locale,
        LocalizedSlugService $localizedSlugs,
        LocalizedContent $content,
        FeatureGate $features,
        SeoMetaFactory $seo
    ) use ($postDetailAction, $articleSlug) {
        return $postDetailAction($locale, $articleSlug, $localizedSlugs, $content, $features, $seo);
    })->name('posts.legacy-'.$idx);
}

Route::get('trang/{slug}', function (
    string $locale,
    string $slug,
    PageController $controller
) {
    return $controller->show($locale, $slug);
})->where('slug', '[A-Za-z0-9\-_]+')->name('pages.legacy-show');
