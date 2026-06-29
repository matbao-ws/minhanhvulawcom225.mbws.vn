<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Review::truncate();

        $products = Product::all();

        if ($products->isEmpty()) {
            return;
        }

        $names = [
            'Nguyễn Văn Nam', 'Phạm Thị Mai', 'Trần Hữu Kiên', 'Lê Thị Thu',
            'Hoàng Văn Bách', 'Đỗ Thị Thảo', 'Bùi Văn Lâm', 'Vũ Thị Dung'
        ];

        $emails = [
            'nam.nguyen@example.com', 'mai.pham@example.com', 'kien.tran@example.com', 'thu.le@example.com',
            'bach.hoang@example.com', 'thao.do@example.com', 'lam.bui@example.com', 'dung.vu@example.com'
        ];

        $comments = [
            'Sản phẩm rất đẹp, đóng gói cẩn thận. Shop phục vụ rất nhiệt tình!',
            'Giao hàng nhanh, chất lượng đúng như mô tả. Sẽ tiếp tục ủng hộ.',
            'Sử dụng rất tốt, bền đẹp. Đáng đồng tiền bát gạo.',
            'Sản phẩm tạm ổn, hơi xước nhẹ nhưng dùng vẫn tốt.',
            'Tuyệt vời ông mặt trời! Đánh giá 5 sao cho shop.',
            'Chất lượng tuyệt hảo, giá cả phải chăng.',
            'Hàng rất xịn sò, đóng gói chắc chắn cực kỳ.',
            'Mới nhận hàng chưa dùng thử nhưng nhìn chung thiết kế đẹp, sang trọng.'
        ];

        $user = User::first();

        foreach ($products as $product) {
            foreach (range(1, rand(2, 4)) as $index) {
                $nameIndex = array_rand($names);
                Review::create([
                    'product_id' => $product->id,
                    'user_id' => $index % 2 === 0 && $user ? $user->id : null,
                    'customer_name' => $names[$nameIndex],
                    'customer_email' => $emails[$nameIndex],
                    'rating' => rand(3, 5), // Seed high ratings
                    'comment' => $comments[array_rand($comments)],
                    'is_visible' => true,
                ]);
            }
        }
    }
}
