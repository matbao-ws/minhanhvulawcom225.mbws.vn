<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['Nguyễn Minh Anh', 'minhanh.customer@example.test', '0909123456', '12 Nguyễn Huệ, Phường Sài Gòn, TP. Hồ Chí Minh'],
            ['Trần Quốc Bảo', 'quocbao.customer@example.test', '0912345678', '85 Lê Lợi, Phường Bến Thành, TP. Hồ Chí Minh'],
            ['Lê Thu Hà', 'thuha.customer@example.test', '0987654321', '24 Trần Phú, Phường Hà Đông, Hà Nội'],
            ['Phạm Hoàng Nam', 'hoangnam.customer@example.test', '0938123456', '118 Nguyễn Văn Linh, Phường Hải Châu, Đà Nẵng'],
            ['Võ Ngọc Lan', 'ngoclan.customer@example.test', '0977123456', '45 Hai Bà Trưng, Phường Ninh Kiều, Cần Thơ'],
            ['Đặng Gia Huy', 'giahuy.customer@example.test', '0966123456', '72 Lạch Tray, Phường Ngô Quyền, Hải Phòng'],
            ['Bùi Thanh Thảo', 'thanhthao.customer@example.test', '0945123456', '31 Phan Đình Phùng, Phường Thuận Hóa, Huế'],
            ['Đỗ Đức Long', 'duclong.customer@example.test', '0923123456', '206 Cách Mạng Tháng Tám, Phường Bình Dương, TP. Hồ Chí Minh'],
        ];

        DB::transaction(function () use ($customers): void {
            foreach ($customers as $index => [$name, $email, $phone, $address]) {
                $customer = User::query()->where('email', $email)->first();
                if ($customer && $customer->role_id !== null) {
                    $this->command?->warn("Bỏ qua {$email} vì email đang thuộc tài khoản admin.");

                    continue;
                }

                if (! $customer) {
                    $customer = User::query()->create([
                        'role_id' => null,
                        'name' => $name,
                        'email' => $email,
                        'preferred_locale' => 'vi',
                        'password' => Hash::make('Customer@12345'),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]);
                }

                UserAddress::query()->updateOrCreate(
                    ['user_id' => $customer->id, 'customer_phone' => $phone],
                    ['customer_name' => $name, 'address' => $address, 'is_default' => true],
                );

                Order::query()->updateOrCreate(
                    ['order_number' => 'CUSTOMER-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $customer->id,
                        'locale' => 'vi',
                        'customer_name' => $name,
                        'customer_email' => $email,
                        'customer_phone' => $phone,
                        'shipping_address' => $address,
                        'payment_method' => 'cod',
                        'payment_status' => $index % 3 === 0 ? 'pending' : 'paid',
                        'status' => $index % 3 === 0 ? 'processing' : 'completed',
                        'subtotal' => 250000 + ($index * 75000),
                        'shipping_fee' => 30000,
                        'discount' => 0,
                        'grand_total' => 280000 + ($index * 75000),
                        'notes' => 'Dữ liệu khách hàng mẫu phục vụ kiểm thử.',
                    ],
                );
            }
        });
    }
}
