<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OasisProductSeeder extends Seeder
{
    use \Database\Seeders\Concerns\ClearsLocalizedSlugs;

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Product::truncate();
        Category::truncate();
        Brand::truncate();
        Schema::enableForeignKeyConstraints();

        $this->clearLocalizedSlugs([Product::class, Category::class, Brand::class]);

        // 1. Create A&O Corporation Brand
        $brand = Brand::create([
            'name' => 'A&O Corporation',
            'slug' => 'a-o-corporation',
            'description' => 'Thương hiệu vật liệu xây dựng hàng đầu',
            'is_active' => true,
        ]);

        // 2. Create Categories
        $insulation = Category::create([
            'name' => [
                'vi' => 'Vật liệu cách nhiệt',
                'en' => 'INSULATION',
                'ko' => '단열재',
            ],
            'slug' => 'insulation',
            'description' => [
                'vi' => 'Các dòng vật liệu cách nhiệt, cách âm cao cấp phục vụ xây dựng xanh.',
                'en' => 'Premium thermal and acoustic insulation materials for green construction.',
                'ko' => '친환경 건축을 위한 프리미엄 열 및 음향 단열재.',
            ],
            'image_url' => '/images/categories/insulation.png',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $exterior = Category::create([
            'name' => [
                'vi' => 'Hệ thống ngoài trời',
                'en' => 'EXTERIOR SYSTEM',
                'ko' => '외장 시스템',
            ],
            'slug' => 'exterior-system',
            'description' => [
                'vi' => 'Vật liệu ốp lát trang trí mặt tiền, chống chịu thời tiết khắc nghiệt.',
                'en' => 'Cladding and decoration materials for exteriors, weather-resistant.',
                'ko' => '기후 변화에 강한 외장용 마감 및 데코 자재.',
            ],
            'image_url' => '/images/categories/exterior.png',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $interior = Category::create([
            'name' => [
                'vi' => 'Hệ thống trong nhà',
                'en' => 'INTERIOR SYSTEM',
                'ko' => '내장 시스템',
            ],
            'slug' => 'interior-system',
            'description' => [
                'vi' => 'Giải pháp cách âm, tiêu âm, trang trí không gian trong nhà.',
                'en' => 'Acoustic, noise-reduction, and decoration solutions for indoor spaces.',
                'ko' => '실내 공간을 위한 음향, 소음 감소 및 데코 솔루션.',
            ],
            'image_url' => '/images/categories/interior.png',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        // 3. Create Products
        // Product 1: Glasswool Water Free
        Product::create([
            'category_id' => $insulation->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'GLASSWOOL WATER FREE',
                'en' => 'GLASSWOOL WATER FREE',
                'ko' => 'GLASSWOOL WATER FREE',
            ],
            'slug' => 'glasswool-water-free',
            'sku' => 'AO-GWWF',
            'short_description' => [
                'vi' => 'Bông thủy tinh không thấm nước, hiệu suất cách nhiệt vượt trội.',
                'en' => 'Waterproof glasswool, outstanding thermal insulation performance.',
                'ko' => '방수 글라스울, 우수한 단열 성능.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Bông thủy tinh <strong>Glasswool Water Free</strong> là dòng vật liệu cách nhiệt, cách âm thế hệ mới sở hữu công nghệ chống thấm nước đột phá. Khác với bông thủy tinh truyền thống dễ bị ngậm nước gây xẹp và giảm hiệu suất, sản phẩm này được xử lý kỵ nước đặc biệt, giữ cho kết cấu luôn khô ráo và bền bỉ trong mọi điều kiện thời tiết.</p><div class="my-6 text-center"><img src="/images/products/glasswool_water_free.png" alt="Glasswool Water Free" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Bông thủy tinh kỵ nước Glasswool Water Free thế hệ mới</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ưu điểm vượt trội</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Kháng nước tuyệt đối:</strong> Ngăn ngừa tích tụ hơi ẩm, chống ẩm mốc và duy trì hệ số cách nhiệt lâu dài.</li><li><strong>Cách nhiệt hiệu quả:</strong> Hệ số dẫn nhiệt thấp giúp tiết kiệm năng lượng tối đa cho công trình.</li><li><strong>Không cháy:</strong> Đạt tiêu chuẩn chống cháy tối ưu, bảo vệ an toàn cho nhà xưởng và cao ốc.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ứng dụng thực tế</h4><p class="text-sm">Sản phẩm cực kỳ lý tưởng cho các dự án xây dựng công nghiệp như mái nhà xưởng, hệ thống đường ống dẫn khí HVAC, vách cách âm phòng máy và các khu vực có độ ẩm cao.</p>',
                'en' => '<p class="mb-4 text-base"><strong>Glasswool Water Free</strong> is a next-generation thermal and acoustic insulation material featuring breakthrough waterproof technology. Unlike traditional glasswool, which absorbs moisture and loses its insulation value, this product is treated with special hydrophobic agents to remain dry and effective under any weather conditions.</p><div class="my-6 text-center"><img src="/images/products/glasswool_water_free.png" alt="Glasswool Water Free" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Next-generation Hydrophobic Glasswool Water Free</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Key Advantages</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Absolute Waterproofing:</strong> Prevents moisture accumulation, mold growth, and sustains long-term thermal resistance.</li><li><strong>Thermal Insulation:</strong> Very low thermal conductivity helps optimize energy efficiency for buildings.</li><li><strong>Non-combustible:</strong> Meets stringent fire protection standards, enhancing structural safety.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Ideal for factory roofs, HVAC duct systems, mechanical room sound insulation, and highly humid environments.</p>',
                'ko' => '<p class="mb-4 text-base"><strong>Glasswool Water Free</strong>는 혁신적인 수분 방지 공법을 적용한 차세대 열 및 음향 단열재입니다. 수분을 머금어 쉽게 뭉치고 성능이 떨어지는 기존 글라스울과 달리, 특수 발수 처리 공정을 거쳐 다습한 기후 조건에서도 구조적 건조함과 탁월한 단열 성능을 유지합니다.</p><div class="my-6 text-center"><img src="/images/products/glasswool_water_free.png" alt="Glasswool Water Free" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">차세대 발수 글라스울 워터 프리</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">주요 장점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>완벽한 방수 성능:</strong> 내부 습기 응축과 곰팡이 번식을 억제하여 단열재의 수명을 극대화합니다.</li><li><strong>뛰어난 열 효율성:</strong> 극히 낮은 열전도율로 빌딩 및 공장의 냉난방 비용을 획기적으로 낮춥니다.</li><li><strong>안전한 불연재:</strong> 최고 수준의 화재 안전 등급을 획득하여 안심하고 시공할 수 있습니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">시공 적용 분야</h4><p class="text-sm">산업용 공장 지붕, 공조 설비 덕트 보온, 기계실 방음벽 및 고온 다습한 실내외 주요 골조 단열에 널리 사용됩니다.</p>',
            ],
            'image_url' => '/images/products/glasswool_water_free.png',
            'price' => 1250000.00,
            'compare_at_price' => 1400000.00,
            'stock_quantity' => 100,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 2: Glasswool
        Product::create([
            'category_id' => $insulation->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'GLASSWOOL',
                'en' => 'GLASSWOOL',
                'ko' => 'GLASSWOOL',
            ],
            'slug' => 'glasswool',
            'sku' => 'AO-GW',
            'short_description' => [
                'vi' => 'Bông thủy tinh cách nhiệt cách âm tiêu chuẩn.',
                'en' => 'Standard thermal and acoustic insulation glasswool.',
                'ko' => '표준 열 및 음향 단열용 글라스울.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Bông thủy tinh <strong>Glasswool</strong> tiêu chuẩn là sự lựa chọn hàng đầu cho các dự án xây dựng dân dụng và công nghiệp cần giải pháp cách nhiệt, cách âm hiệu quả với chi phí tối ưu. Được liên kết chặt chẽ từ các sợi thủy tinh siêu mịn, sản phẩm mang lại khả năng ngăn chặn truyền nhiệt và giảm thiểu tiếng ồn cực tốt.</p><div class="my-6 text-center"><img src="/images/products/glasswool.png" alt="Glasswool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Bông thủy tinh cách nhiệt tiêu chuẩn</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Đặc tính kỹ thuật</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Cách âm vượt trội:</strong> Cấu trúc dạng sợi giúp hấp thụ và triệt tiêu sóng âm đi qua hệ thống tường và trần.</li><li><strong>Chống cháy tốt:</strong> Khả năng chống chịu nhiệt độ cao giúp bảo vệ kết cấu thép bên trong công trình khi xảy ra sự cố.</li><li><strong>Thân thiện môi trường:</strong> Không chứa amiăng, an toàn cho thợ thi công và người sử dụng.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ứng dụng phù hợp</h4><p class="text-sm">Sử dụng để lót sàn, vách trần thạch cao, bọc ống gió cách nhiệt cho hệ thống điều hòa trung tâm văn phòng, nhà xưởng.</p>',
                'en' => '<p class="mb-4 text-base">Standard <strong>Glasswool</strong> is a top-choice insulation solution for residential and industrial projects requiring budget-friendly thermal and acoustic management. Composed of fine, resilient glass fibers, it provides high resistance to heat flow and significantly minimizes sound transmission.</p><div class="my-6 text-center"><img src="/images/products/glasswool.png" alt="Glasswool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Standard Thermal Insulation Glasswool</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Technical Highlights</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Excellent Acoustic Control:</strong> Fibrous structure dampens and absorbs sound waves passing through walls and ceilings.</li><li><strong>Fire Protection:</strong> Highly heat resistant, protecting structural steel in case of fire incidents.</li><li><strong>Eco-friendly & Safe:</strong> Asbestos-free, safe to handle and install under standard guidelines.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Key Applications</h4><p class="text-sm">Ideal for ceiling and wall partitions, under-roof installation in factories, and thermal wraps for central air conditioning duct systems.</p>',
                'ko' => '<p class="mb-4 text-base">표준 <strong>Glasswool</strong>은 경제적이면서도 확실한 단열 및 차음 성능을 제공하여 주거용 및 산업용 건축에 가장 널리 사용되는 베스트셀러 제품입니다. 탄력성 높은 미세 유리섬유가 촘촘하게 얽혀 열의 흐름을 차단하고 외부 소음 유입을 최소화합니다.</p><div class="my-6 text-center"><img src="/images/products/glasswool.png" alt="Glasswool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">표준 단열재 글라스울</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">기술적 장점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>탁월한 흡음 성능:</strong> 다공성 섬유 구조가 벽체와 천장을 통과하는 소음 에너지를 효과적으로 흡수합니다.</li><li><strong>검증된 방화 기능:</strong> 고온을 견디며 연소를 지연시켜 화재 발생 시 대피 시간을 확보해 줍니다.</li><li><strong>친환경성 및 안전성:</strong> 석면 무함유 친환경 소재로 시공자와 거주자 모두에게 안전합니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">주요 적용 용도</h4><p class="text-sm">석고보드 칸막이벽 내부 충진, 주택 및 공장 지붕 하부 단열, 빌딩 중앙 집중식 냉난방 공조관 보온재로 널리 시공됩니다.</p>',
            ],
            'image_url' => '/images/products/glasswool.png',
            'price' => 950000.00,
            'compare_at_price' => 1100000.00,
            'stock_quantity' => 150,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 3: Ceramic Wool
        Product::create([
            'category_id' => $insulation->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'CERAMIC WOOL',
                'en' => 'CERAMIC WOOL',
                'ko' => 'CERAMIC WOOL',
            ],
            'slug' => 'ceramic-wool',
            'sku' => 'AO-CW',
            'short_description' => [
                'vi' => 'Bông gốm chịu nhiệt độ cao siêu hạng lên tới 1260 độ C.',
                'en' => 'Super high heat-resistant ceramic wool up to 1260°C.',
                'ko' => '최대 1260°C의 초고온을 견디는 세라믹울.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Bông gốm siêu chịu nhiệt <strong>Ceramic Wool</strong> (còn gọi là Ceramic Blanket) là vật liệu bảo ôn cao cấp chuyên dụng cho các môi trường nhiệt độ cực cao lên đến 1260°C. Được sản xuất từ các sợi gốm silicat tinh khiết thông qua quá trình nung thổi cường độ cao, sản phẩm sở hữu tính bền nhiệt tuyệt đối và hệ số tích nhiệt cực thấp.</p><div class="my-6 text-center"><img src="/images/products/ceramic_wool.png" alt="Ceramic Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Bông gốm siêu chịu nhiệt Ceramic Wool 1260°C</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Tính năng nổi bật</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Khả năng chịu nhiệt cực đại:</strong> Hoạt động ổn định liên tục trong các lò công nghiệp có nhiệt độ lên đến 1260°C.</li><li><strong>Độ bền cơ học cao:</strong> Kết cấu đan xen chặt chẽ giúp cuộn bông gốm không bị rách hay biến dạng khi thi công kéo căng.</li><li><strong>Chống hóa chất:</strong> Kháng hầu hết các loại axit và chất ăn mòn hóa học (ngoại trừ axit huỳnh thạch và phốt-phô).</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ứng dụng tiêu biểu</h4><p class="text-sm">Bọc bảo ôn lò nung sắt thép, lò gốm sứ, lò hơi công nghiệp, đường ống dẫn hơi áp suất cao và các thiết bị chịu nhiệt trong ngành luyện kim, hóa chất.</p>',
                'en' => '<p class="mb-4 text-base">High-temperature <strong>Ceramic Wool</strong> (also known as Ceramic Blanket) is a premium insulation material specialized for extreme heat environments up to 1260°C. Manufactured from high-purity alumina-silica fibers, this product delivers excellent thermal stability and extremely low heat storage.</p><div class="my-6 text-center"><img src="/images/products/ceramic_wool.png" alt="Ceramic Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Ultra High Temperature Ceramic Wool 1260°C</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Key Features</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Extreme Temperature Stability:</strong> Performs reliably in continuous industrial furnace environments up to 1260°C.</li><li><strong>High Tensile Strength:</strong> Woven structures ensure the blanket resists tearing or warping during tight installations.</li><li><strong>Chemical Resistance:</strong> Unaffected by most acids and corrosive chemicals (except hydrofluoric and phosphoric acids).</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Widely applied for lining industrial furnaces, ceramic kilns, heavy-duty boilers, high-pressure steam pipelines, and thermal insulation in metallurgical processes.</p>',
                'ko' => '<p class="mb-4 text-base">초고온용 <strong>Ceramic Wool</strong>(세라믹 블랭킷)은 최대 1260°C에 이르는 극도의 열적 환경을 견디도록 개발된 프리미엄 내화 단열재입니다. 고순도 규산알루미늄 섬유를 특수 공법으로 압착 가공하여 열전도도가 낮고 열 보존력이 뛰어나 에너지를 대폭 절감해 줍니다.</p><div class="my-6 text-center"><img src="/images/products/ceramic_wool.png" alt="Ceramic Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">1260°C 초고온용 세라믹울</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">주요 제품 특징</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>독보적인 열 내구성:</strong> 1200도 이상의 초고온 용해로 및 열처리로 내부에서도 화학적 성질이 변하지 않고 유지됩니다.</li><li><strong>우수한 기계적 강도:</strong> 치밀하게 얽힌 섬유 구조 덕분에 인장 강도가 높아 시공 시 찢어지거나 마모되지 않습니다.</li><li><strong>강력한 내화학성:</strong> 불산 및 인산을 제외한 대부분의 산성 물질 및 화학 부식성 가스에 뛰어난 저항력을 지닙니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">추천 시공 현장</h4><p class="text-sm">제철/제강 산업의 용해로 보온, 도자기 가마 내벽 라이닝, 발전소 고압 스팀 배관 보온, 정유 및 화학 공장의 열 차단벽에 적합합니다.</p>',
            ],
            'image_url' => '/images/products/ceramic_wool.png',
            'price' => 2400000.00,
            'stock_quantity' => 80,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 4: Mineral Wool
        Product::create([
            'category_id' => $insulation->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'MINERAL WOOL',
                'en' => 'MINERAL WOOL',
                'ko' => 'MINERAL WOOL',
            ],
            'slug' => 'mineral-wool',
            'sku' => 'AO-MW',
            'short_description' => [
                'vi' => 'Bông khoáng cách âm chống cháy tỷ trọng cao.',
                'en' => 'High-density acoustic and fire-resistant rockwool.',
                'ko' => '고밀도 방음 및 방화용 미네랄울.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Bông khoáng <strong>Mineral Wool</strong> (Rockwool) là giải pháp cách âm chuyên nghiệp và chống cháy lan thụ động hàng đầu hiện nay. Được sản xuất từ đá basalt và quặng nung chảy ở nhiệt độ cao, bông khoáng sở hữu tỷ trọng lớn, kết cấu vững chắc, mang lại hiệu quả tiêu âm và ngăn cháy cực kỳ ấn tượng.</p><div class="my-6 text-center"><img src="/images/products/mineral_wool.png" alt="Mineral Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Bông khoáng cách âm chống cháy Mineral Wool</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Lợi ích cốt lõi</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Chống cháy lan chủ động:</strong> Điểm nóng chảy vượt trội trên 1000°C giúp ngăn chặn đám cháy phát triển rộng.</li><li><strong>Tiêu âm tối ưu:</strong> Tỷ trọng cao giúp triệt tiêu các tần số âm thanh từ trung đến trầm, giảm độ vang vọng trong không gian.</li><li><strong>Cách nhiệt bền bỉ:</strong> Không bị co ngót hay suy giảm khả năng bảo ôn theo thời gian sử dụng.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Khuyên dùng cho</h4><p class="text-sm">Hệ vách tiêu âm phòng thu âm, rạp chiếu phim, quán karaoke, phòng máy phát điện công nghiệp đòi hỏi khắt khe về cách âm và an toàn phòng cháy chữa cháy.</p>',
                'en' => '<p class="mb-4 text-base"><strong>Mineral Wool</strong> (Rockwool) is a leading solution for professional acoustic management and passive fire protection. Engineered from volcanic basalt rock and slag melted at extreme temperatures, it features high density and rigid structural integrity, ensuring optimal sound absorption and flame barrier performance.</p><div class="my-6 text-center"><img src="/images/products/mineral_wool.png" alt="Mineral Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">High Density Acoustic Mineral Wool</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Core Value</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Fire Containment:</strong> Extremely high melting point above 1000°C effectively prevents flame spread.</li><li><strong>Sound Absorption:</strong> Denser composition dampens mid-to-low sound frequencies, minimizing echo.</li><li><strong>Durability:</strong> Retains its insulation capacity and shape over decades without sagging.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Recommended For</h4><p class="text-sm">Ideal for soundproofing recording studios, cinemas, karaoke lounges, and generator enclosures requiring fire safety and sound damping.</p>',
                'ko' => '<p class="mb-4 text-base">고밀도 <strong>Mineral Wool</strong>(락울/미네랄울)은 프로페셔널한 음향 설계와 방화 시스템 구축을 위한 최적의 건축 자재입니다. 천연 현무암과 고로슬래그를 고온에서 융해하여 섬유 형태로 제작한 자재로, 압도적인 소음 차단(NRC) 및 화재 전파 지연 효과를 발휘합니다.</p><div class="my-6 text-center"><img src="/images/products/mineral_wool.png" alt="Mineral Wool" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">고밀도 방음 및 방화용 미네랄울</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">핵심 특장점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>강력한 화재 확산 방지:</strong> 녹는점이 1000°C 이상으로 매우 높아, 화재 시 구조물 붕괴를 예방하고 불길이 번지는 것을 원천 차단합니다.</li><li><strong>최상의 소음 흡수력:</strong> 조밀한 중-저주파 대역 차음 효과로 내부 소리가 밖으로 새거나 외부 노이즈가 유입되는 것을 방지합니다.</li><li><strong>반영구적 수명:</strong> 장기간 사용 시에도 수축이나 꺼짐 현상 없이 초기 밀도와 구조적 강도를 그대로 유지합니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">추천 적용처</h4><p class="text-sm">방음이 생명인 레코딩 스튜디오, 영화관, 고출력 발전기실 및 건물 외벽의 방화 구획선 시공에 적극 추천됩니다.</p>',
            ],
            'image_url' => '/images/products/mineral_wool.png',
            'price' => 1550000.00,
            'stock_quantity' => 120,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 5: Isopink
        Product::create([
            'category_id' => $insulation->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'ISOPINK',
                'en' => 'ISOPINK',
                'ko' => 'ISOPINK',
            ],
            'slug' => 'isopink',
            'sku' => 'AO-IP',
            'short_description' => [
                'vi' => 'Tấm cách nhiệt XPS màu hồng cường độ nén cao.',
                'en' => 'High compression strength pink XPS insulation board.',
                'ko' => '고압축 강도의 핑크색 XPS 단열 보드.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Tấm xốp cách nhiệt <strong>Isopink</strong> (XPS) màu hồng cao cấp là vật liệu chống thất thoát nhiệt lý tưởng cho các sàn bê tông nặng, móng, tường tầng hầm nhờ cường độ nén chịu lực cực cao. Được cấu tạo từ hạt nhựa Polystyrene cùng bọt nở thông qua công nghệ đùn ép tiên tiến, sản phẩm có cấu trúc hạt kín 100% không cho nước và hơi ẩm đi qua.</p><div class="my-6 text-center"><img src="/images/products/isopink.png" alt="Isopink" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Tấm cách nhiệt cường độ chịu nén cao Isopink XPS</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ưu điểm nổi bật</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Khả năng chịu nén siêu hạng:</strong> Chịu đựng được tải trọng nén cực lớn của các lớp sàn bê tông dày mà không bị biến dạng.</li><li><strong>Kháng ẩm tối đa:</strong> Hệ số hấp thụ nước gần như bằng 0, ngăn cản hiện tượng ẩm mốc và rò rỉ nước ngầm tại tầng hầm.</li><li><strong>Hiệu suất cách nhiệt vượt trội:</strong> Giữ nhiệt độ phòng luôn ổn định, giảm thất thoát năng lượng điều hòa.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Lĩnh vực ứng dụng</h4><p class="text-sm">Lót sàn kho lạnh, cách nhiệt mái bê tông đổ phẳng, chống nồm ẩm cho sàn nhà dân dụng, cách nhiệt nền móng và tường tầng hầm.</p>',
                'en' => '<p class="mb-4 text-base">Premium pink <strong>Isopink</strong> (XPS) foam board is the ultimate thermal barrier for heavy-load concrete floors, foundations, and basement walls, engineered with exceptionally high compressive strength. Formed through continuous extrusion of polystyrene with closed-cell structure, it effectively blocks heat, water, and humidity.</p><div class="my-6 text-center"><img src="/images/products/isopink.png" alt="Isopink" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">High Compression Strength Isopink XPS Board</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Key Benefits</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Superb Compressive Strength:</strong> Withstands heavy concrete slab loads and high mechanical pressure without deformation.</li><li><strong>Water & Moisture Block:</strong> Near-zero water absorption prevents underground moisture seepage and structural mold.</li><li><strong>Energy Conservation:</strong> Excellent R-value minimizes indoor heat loss or gain, lowering HVAC utility costs.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Perfect for cold storage floor insulation, flat concrete roof insulation, basement foundation walls, and moisture control beneath residential flooring.</p>',
                'ko' => '<p class="mb-4 text-base">벽산 정품 <strong>Isopink</strong> (아이소핑크) 압출법 보온판은 고압축 강도와 우수한 습기 차단 기능을 지녀 콘크리트 바닥, 기초 매트, 지하실 외벽 단열에 가장 이상적인 분홍색 XPS 폼 보드입니다. 완전한 미세 독립 기포 구조로 형성되어 장기적인 열전도율 변화가 없으며 물을 전혀 흡수하지 않습니다.</p><div class="my-6 text-center"><img src="/images/products/isopink.png" alt="Isopink" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">벽산 정품 고강도 아이소핑크 XPS 보드</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">제품 강점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>압도적인 압축 강도:</strong> 콘크리트 하중 및 기계 진동 하에서도 변형이나 함몰 없이 지지 구조를 형성합니다.</li><li><strong>완벽한 투습 저항:</strong> 수분 흡수율이 제로에 가까워 지하수 침투와 벽체 결로에 따른 곰팡이 발생을 막아줍니다.</li><li><strong>장기 단열성 유지:</strong> 충진 가스의 안정성과 독립 기포 구조 덕분에 시간이 흘러도 초기 단열 등급을 높게 유지합니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">시공 적용 범위</h4><p class="text-sm">냉동창고 바닥 보온, 아파트 및 빌딩의 옥상 평슬래브 옥상 단열, 지하실 옹벽 방습 및 바닥 nồm 억제용 시공.</p>',
            ],
            'image_url' => '/images/products/isopink.png',
            'price' => 380000.00,
            'stock_quantity' => 500,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 6: Bace Panel
        Product::create([
            'category_id' => $exterior->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'BACE PANEL',
                'en' => 'BACE PANEL',
                'ko' => 'BACE PANEL',
            ],
            'slug' => 'bace-panel',
            'sku' => 'AO-BP',
            'short_description' => [
                'vi' => 'Tấm ốp xi măng sợi chịu lực cao ngoài trời.',
                'en' => 'High-strength fiber cement exterior cladding panel.',
                'ko' => '고강도 섬유 시멘트 외장 패널.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Tấm ốp ngoại thất <strong>Bace Panel</strong> là vật liệu kiến trúc hiện đại bằng xi măng sợi chịu lực cao ngoài trời. Được sản xuất với công nghệ ép thủy tinh cường lực và sấy chưng áp Autoclave, sản phẩm có thể chống chọi với mọi điều kiện khí hậu nóng ẩm, mưa bão khắc nghiệt mà không hề bị cong vênh hay mối mọt.</p><div class="my-6 text-center"><img src="/images/products/exterior_wood_wall.png" alt="Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Tấm ốp xi măng sợi Bace Panel chống chịu thời tiết ngoài trời</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Tính chất nổi bật</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Bền bỉ tuyệt đối:</strong> Kháng tia cực tím (UV), không phai màu hay nứt vỡ dưới ánh nắng mặt trời trực tiếp.</li><li><strong>Chống thấm nước & Chống cháy:</strong> Đạt tiêu chuẩn chống cháy loại A, ngăn thấm nước mưa hoàn hảo bảo vệ hệ tường gạch bên trong.</li><li><strong>Thẩm mỹ sang trọng:</strong> Bề mặt phẳng mịn có thể sơn phủ màu sắc linh hoạt, tạo điểm nhấn kiến trúc độc đáo.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ứng dụng kiến trúc</h4><p class="text-sm">Ốp lát trang trí mặt tiền villa, biệt thự nghỉ dưỡng, tòa nhà văn phòng, làm vách ngăn bao ngoài chịu lực cho nhà lắp ghép thông minh.</p>',
                'en' => '<p class="mb-4 text-base"><strong>Bace Panel</strong> exterior cladding is a modern architectural solution made of high-strength fiber cement for outdoor applications. Engineered with advanced autoclave curing technology, it resists warping, rotting, and cracking even under extreme weather, storms, and high humidity.</p><div class="my-6 text-center"><img src="/images/products/exterior_wood_wall.png" alt="Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Weather-resistant Bace Panel Fiber Cement Exterior Cladding</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Core Performance</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>All-Weather Durability:</strong> UV resistant, maintains its integrity and look without cracking or fading under direct sunlight.</li><li><strong>Fire & Water Barrier:</strong> Class A fire rating with excellent rain-proofing qualities to shield inner structures.</li><li><strong>Modern Aesthetics:</strong> Features a clean, smooth texture that supports custom paints, enabling sleek modern building styles.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Ideal for villa exterior facades, commercial building fronts, resort siding, and external structural sheets for pre-engineered buildings.</p>',
                'ko' => '<p class="mb-4 text-base">외장용 <strong>Bace Panel</strong>(베이스 패널)은 가혹한 기후 변화에 대응할 수 있도록 고압 성형 및 오토클레이브 양생 공법으로 제작된 프리미엄 고강도 섬유 시멘트 외벽 패널입니다. 습기와 온도 변화에 따른 변형이나 뒤틀림, 부식이 전혀 없어 오랫동안 깨끗한 외관을 유지해 줍니다.</p><div class="my-6 text-center"><img src="/images/products/exterior_wood_wall.png" alt="Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">기후 변화에 강한 외장 베이스 패널</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">기술적 우위</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>독보적인 전천후 내구성:</strong> 강력한 자외선 차단 능력으로 햇빛 노출 시에도 탈색이나 미세 균열이 일어나지 않습니다.</li><li><strong>방화 및 수분 차단:</strong> 비가연성(Class A) 인증을 받았으며 빗물이 내부 철골이나 콘크리트 벽으로 스며드는 것을 원천 예방합니다.</li><li><strong>고품격 마감 스타일:</strong> 모던하고 미려한 표면 마감을 자랑하며, 건축가의 콘셉트에 맞는 자유로운 도장이 가능합니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">설계 권장 대상</h4><p class="text-sm">단독 주택 및 타운하우스 건물 외벽 마감, 상업용 고층 빌딩 로비 및 파사드, 조립식 모듈러 하우스 주 구조체 외벽 마감.</p>',
            ],
            'image_url' => 'https://abc-oasis.com/wp-content/uploads/2025/11/Anh-bace-panel-1.png',
            'price' => 680000.00,
            'stock_quantity' => 200,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 7: Green Bace Panel
        Product::create([
            'category_id' => $exterior->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'GREEN BACE PANEL',
                'en' => 'GREEN BACE PANEL',
                'ko' => 'GREEN BACE PANEL',
            ],
            'slug' => 'green-bace-panel',
            'sku' => 'AO-GBP',
            'short_description' => [
                'vi' => 'Tấm ốp sinh thái ngoài trời thân thiện với môi trường.',
                'en' => 'Eco-friendly green exterior cladding panel.',
                'ko' => '친환경 그린 외장 클래딩 패널.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Tấm ốp sinh thái <strong>Green Bace Panel</strong> là bước đi tiên phong trong kỷ nguyên vật liệu xây dựng xanh. Sản phẩm thừa hưởng toàn bộ ưu điểm chịu lực và bền bỉ của tấm xi măng sợi cao cấp, đồng thời được tối ưu hóa quy trình sản xuất bằng việc sử dụng 100% nguyên liệu thô tự nhiên không chứa amiăng độc hại và bổ sung các thành phần tái chế xanh bảo vệ môi trường.</p><div class="my-6 text-center"><img src="https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-BIA.png" alt="Green Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Tấm ốp ngoại thất sinh thái Green Bace Panel</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Đặc tính nổi bật</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Đạt chứng chỉ xanh:</strong> Góp phần tích lũy điểm thưởng cho các dự án xin cấp chứng nhận LEED hoặc LOTUS.</li><li><strong>Kháng ẩm, kháng mốc:</strong> Khả năng ngăn nước ngấm vượt trội, không phát triển nấm mốc gây ảnh hưởng chất lượng không khí xung quanh.</li><li><strong>Hiệu quả kinh tế lâu dài:</strong> Tuổi thọ vật liệu trên 30 năm, giảm chi phí bảo trì định kỳ cho công trình.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ứng dụng phổ biến</h4><p class="text-sm">Làm vách bao ngoài trang trí mặt đứng tòa nhà, ốp hành lang ngoài trời cho trường học, bệnh viện, khu sinh thái nghỉ dưỡng xanh.</p>',
                'en' => '<p class="mb-4 text-base"><strong>Green Bace Panel</strong> is a pioneering eco-friendly cladding solution designed for sustainable green architecture. Combining the exceptional structural strength of premium fiber cement panels with green production practices, it utilizes asbestos-free raw materials and recycled composites to minimize environmental footprints.</p><div class="my-6 text-center"><img src="https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-BIA.png" alt="Green Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Eco-friendly Green Bace Panel Cladding</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Eco-Performance Highlights</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Green Certified:</strong> Helps acquire LEED/LOTUS points for sustainable green construction projects.</li><li><strong>Anti-Mold & Moisture Proof:</strong> High water resistance prevents dampness and harmful micro-organism growth on exterior surfaces.</li><li><strong>Cost-Effective Lifetime:</strong> Exceeds 30 years of material lifespan, drastically lowering lifetime maintenance costs.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Ideal for external walls of schools, hospitals, eco-resorts, and facades of public buildings advocating environmental sustainability.</p>',
                'ko' => '<p class="mb-4 text-base">친환경 <strong>Green Bace Panel</strong>(그린 베이스 패널)은 친환경 저탄소 녹색 건축을 위한 혁신적인 외장재입니다. 프리미엄 섬유 시멘트 패널 고유의 뛰어난 내구성을 보장함과 동시에 100% 무석면 친환경 천연 원료와 재활용 녹색 바인더 소재를 융합하여 제조함으로써 환경에 미치는 영향을 최소화하였습니다.</p><div class="my-6 text-center"><img src="https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-BIA.png" alt="Green Bace Panel" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">저탄소 녹색 건축 외장용 그린 베이스 패널</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">에코 제품 강점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>친환경 건축 인증 획득:</strong> LEED 및 다양한 국내외 친환경 건축물 등급 심사 시 점수 획득에 크게 기여합니다.</li><li><strong>뛰어난 방습/항곰팡이 성능:</strong> 외부 수분 침투를 차단하여 유해 곰팡이나 조류의 번식을 사전에 차단합니다.</li><li><strong>경제적이고 지속 가능한 소재:</strong> 30년 이상의 반평생 수명을 제공하여 건축물 리모델링 및 유지 관리 비용을 절감합니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">추천 적용 설계</h4><p class="text-sm">에코 빌리지 리조트 파사드, 친환경 학교 및 병원 외벽, 녹색 도시 계획 프로젝트 건물 외장 옹벽 데코레이션.</p>',
            ],
            'image_url' => 'https://abc-oasis.com/wp-content/uploads/2025/11/anh-1-BIA.png',
            'price' => 750000.00,
            'stock_quantity' => 150,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        // Product 8: Ventwall
        Product::create([
            'category_id' => $interior->id,
            'brand_id' => $brand->id,
            'name' => [
                'vi' => 'VENTWALL',
                'en' => 'VENTWALL',
                'ko' => 'VENTWALL',
            ],
            'slug' => 'ventwall',
            'sku' => 'AO-VW',
            'short_description' => [
                'vi' => 'Hệ thống vách thông gió nội thất độc đáo.',
                'en' => 'Unique interior ventilated partition wall system.',
                'ko' => '독특한 실내 환기형 칸막이벽 시스템.',
            ],
            'description' => [
                'vi' => '<p class="mb-4 text-base">Hệ vách thông gió và cách âm nội thất <strong>Ventwall</strong> mang lại giải pháp không gian thông minh, mang tính đột phá cho các văn phòng và chung cư cao cấp. Thiết kế độc đáo cho phép luồng không khí lưu thông tự nhiên giữa các phòng nhưng vẫn duy trì khả năng tán âm, giảm thiểu tiếng ồn và tạo sự riêng tư cần thiết.</p><div class="my-6 text-center"><img src="/images/products/acoustic_panel.png" alt="Ventwall" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Hệ vách ngăn tiêu âm thông gió Ventwall trong nhà</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ưu điểm thiết kế</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Lưu thông không khí tự nhiên:</strong> Cấu tạo rãnh khí động học độc đáo tạo sự đối lưu gió mát, giảm nóng bức trong các không gian khép kín.</li><li><strong>Cách âm & Tiêu âm nhẹ:</strong> Giảm vang âm hiệu quả, kiến tạo môi trường làm việc yên tĩnh, tập trung.</li><li><strong>Thiết kế thẩm mỹ vượt trội:</strong> Kiểu dáng hiện đại, mang phong cách tối giản Bắc Âu sang trọng, nâng tầm không gian sống.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Ý tưởng thiết kế</h4><p class="text-sm">Làm vách ngăn phân chia khu vực làm việc trong văn phòng, vách trang trí sau kệ Tivi phòng khách, vách ngăn phòng ngủ và phòng làm việc tại nhà.</p>',
                'en' => '<p class="mb-4 text-base">The <strong>Ventwall</strong> ventilated and acoustic partition system provides an innovative spatial solution for modern offices and upscale residential apartments. Its unique aerodynamic layout allows natural air circulation between sections while delivering quality sound dispersion to enhance acoustic privacy.</p><div class="my-6 text-center"><img src="/images/products/acoustic_panel.png" alt="Ventwall" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">Ventwall Interior Acoustic & Ventilated Partition Wall</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Design Merits</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>Natural Airflow:</strong> Specialized ventilation slots maintain natural fresh air circulation, reducing heating in closed zones.</li><li><strong>Sound Control:</strong> Efficiently absorbs high-frequency noise, fostering a quiet and highly focused workspace.</li><li><strong>Premium Minimalist Design:</strong> Sleek aesthetic inspired by Scandinavian minimalism, bringing luxury and elegance.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">Applications</h4><p class="text-sm">Ideal for dividing zones in open offices, feature walls behind home theater systems, and elegant partitions between bedrooms and study rooms.</p>',
                'ko' => '<p class="mb-4 text-base">실내 환기 및 방음 벽체 시스템인 <strong>Ventwall</strong>(벤트월)은 현대식 스마트 오피스와 최고급 주거용 아파트 공간을 위한 소통적 차단막 솔루션입니다. 고유의 공기역학적 슬릿 설계를 통해 실내 공기 순환을 원활히 돕는 한편, 반사되는 회절 소음을 효과적으로 분산 차단하여 업무 몰입도를 향상해 줍니다.</p><div class="my-6 text-center"><img src="/images/products/acoustic_panel.png" alt="Ventwall" class="rounded-xl shadow-lg max-w-full h-auto mx-auto border border-gray-100" style="max-height: 380px; object-fit: cover;" /><span class="text-xs text-gray-500 mt-2 block font-medium">실내 흡음 환기 가벽 시스템 벤트월</span></div><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">설계 장점</h4><ul class="list-disc pl-6 space-y-2 mb-6"><li><strong>자연 대류 순환 통로:</strong> 독독한 내장 통로 디자인이 실내 공기가 정체되지 않고 시원하게 흐르도록 대류 현상을 유도합니다.</li><li><strong>실내 차음 및 난반사 방지:</strong> 잡음 주파수를 여과 흡수하여 소란스럽지 않고 프라이빗한 개인 및 미팅 영역을 보장합니다.</li><li><strong>최상급 북유럽 인테리어 감성:</strong> 미니멀리즘과 모던 스타일링을 극대화한 세련된 격자 디자인으로 공간의 품격을 한 단계 높여줍니다.</li></ul><h4 class="text-lg font-bold text-primary-navy mt-6 mb-3">추천 적용 사례</h4><p class="text-sm">개방형 사무실의 개인 워크스테이션 파티션, 아파트 거실의 홈 시어터 미디어월 장식 가벽, 침실과 실내 공부방 사이의 분리막 시공.</p>',
            ],
            'image_url' => '/images/products/acoustic_panel.png',
            'price' => 890000.00,
            'stock_quantity' => 180,
            'manage_stock' => true,
            'is_active' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);
    }
}
