<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Order::truncate();
        OrderItem::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $products = Product::all();

        // If no products exist, create some mock products
        if ($products->isEmpty()) {
            $category = \App\Models\Category::first() ?: \App\Models\Category::create([
                'slug' => 'chua-phan-loai',
                'name' => ['vi' => 'Chưa phân loại', 'en' => 'Uncategorized'],
                'description' => ['vi' => 'Mặc định', 'en' => 'Default'],
                'is_active' => true,
            ]);

            foreach (range(1, 5) as $i) {
                $products->push(Product::create([
                    'category_id' => $category->id,
                    'name' => [
                        'vi' => 'Sản phẩm mẫu ' . $i,
                        'en' => 'Mock Product ' . $i,
                    ],
                    'slug' => 'san-pham-mau-' . $i . '-' . time(),
                    'sku' => 'PROD-MOCK-' . sprintf('%03d', $i),
                    'price' => $i * 100000.00,
                    'stock_quantity' => 50,
                    'is_active' => true,
                ]));
            }
        }

        $customerNames = [
            'Nguyễn Văn An', 'Trần Thị Bình', 'Lê Hoàng Châu', 'Phạm Minh Đức',
            'Hoàng Anh Tuấn', 'Đặng Ngọc Lan', 'Vũ Quốc Khánh', 'Bùi Tuyết Mai',
            'Đỗ Thanh Hải', 'Ngô Hồng Sơn', 'Lý Kim Chi', 'Phan Văn Đạt'
        ];

        $emails = [
            'an.nguyen@example.com', 'binh.tran@example.com', 'chau.le@example.com', 'duc.pham@example.com',
            'tuan.hoang@example.com', 'lan.dang@example.com', 'khanh.vu@example.com', 'mai.bui@example.com',
            'hai.do@example.com', 'son.ngo@example.com', 'chi.ly@example.com', 'dat.phan@example.com'
        ];

        $phones = [
            '0912345678', '0987654321', '0905123456', '0934567890',
            '0978123456', '0945678901', '0967890123', '0898765432',
            '0888123456', '0868123456', '0858123456', '0848123456'
        ];

        $addresses = [
            '123 Đường Láng, Đống Đa, Hà Nội',
            '456 Nguyễn Thị Minh Khai, Quận 3, TP. Hồ Chí Minh',
            '789 Trần Hưng Đạo, Ninh Kiều, Cần Thơ',
            '12 Lê Lợi, Hải Châu, Đà Nẵng',
            '34 Hùng Vương, Nha Trang, Khánh Hòa',
            '56 Quang Trung, Hồng Bàng, Hải Phòng',
            '78 Hoàng Văn Thụ, Thái Nguyên'
        ];

        $paymentMethods = ['cod', 'bank_transfer', 'online'];
        $statuses = ['pending', 'processing', 'completed', 'cancelled'];

        foreach (range(1, 25) as $i) {
            $customerIndex = array_rand($customerNames);
            $name = $customerNames[$customerIndex];
            $email = $emails[$customerIndex];
            $phone = $phones[$customerIndex];
            $address = $addresses[array_rand($addresses)];
            $method = $paymentMethods[array_rand($paymentMethods)];
            $status = $statuses[array_rand($statuses)];
            
            // Logic for payment status based on order status
            if ($status === 'completed') {
                $paymentStatus = 'paid';
            } elseif ($status === 'cancelled') {
                $paymentStatus = $i % 4 === 0 ? 'paid' : 'pending';
            } else {
                $paymentStatus = $method === 'online' ? 'paid' : 'pending';
            }

            // Create Order
            $order = Order::create([
                'order_number' => 'ORD-2026-' . sprintf('%04d', $i),
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'shipping_address' => $address,
                'payment_method' => $method,
                'payment_status' => $paymentStatus,
                'status' => $status,
                'subtotal' => 0,
                'discount' => $i % 5 === 0 ? 50000.00 : 0,
                'grand_total' => 0,
                'notes' => $i % 3 === 0 ? 'Giao hàng giờ hành chính giúp em.' : null,
                'created_at' => now()->subDays($i)->subHours($i),
            ]);

            // Add 1 to 3 items
            $numItems = rand(1, 3);
            $subtotal = 0;
            $selectedProducts = $products->random(min($numItems, $products->count()));

            foreach ($selectedProducts as $product) {
                $qty = rand(1, 2);
                $price = $product->price;
                $total = $price * $qty;
                $subtotal += $total;

                $localeName = $product->getTranslation('name', 'vi', false) ?: $product->name;
                if (is_array($localeName)) {
                    $prodName = $localeName['vi'] ?? ($localeName['en'] ?? 'Product Name');
                } else {
                    $prodName = $localeName;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $prodName,
                    'sku' => $product->sku,
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $total,
                ]);
            }

            $discount = $order->discount;
            $grandTotal = max(0, $subtotal - $discount);

            $order->update([
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
            ]);
        }
    }
}
