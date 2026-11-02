<?php

namespace Tests\Feature;

use App\Jobs\SendOrderStatusEmail;
use App\Mail\ContactInquiryMail;
use App\Mail\MailSettingsTestMail;
use App\Mail\OrderEmailData;
use App\Mail\OrderStatusMail;
use App\Mail\OwnerNewOrderMail;
use App\Models\ContactSubmission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\EmailAppearance;
use App\Services\MailSettings;
use App\Support\NotificationHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The shop's email design (Quản trị › Giao diện email): looks, texts and which emails go out. */
class EmailAppearanceTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        ProjectSetting::query()->create(['setting_key' => 'shop_name', 'setting_value' => 'Trầm Hương Phú Quý']);
        ProjectSetting::query()->create(['setting_key' => 'contact', 'setting_value' => ['address' => '128 Nguyễn Thị Minh Khai, Q.3', 'phone' => '0909 123 456', 'email' => 'cskh@phuquy.test']]);

        $this->order = Order::query()->create([
            'order_number' => 'DH-24103018', 'locale' => 'vi',
            'customer_name' => 'Nguyễn Minh Anh', 'customer_email' => 'anh@example.test', 'customer_phone' => '0938456712',
            'shipping_address' => '27/4 Lê Văn Sỹ, Q. Phú Nhuận', 'notes' => 'Gọi trước khi giao',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'shipping_carrier' => 'ghn',
            'subtotal' => 1715000, 'discount' => 50000, 'promotion_discount' => 120000, 'shipping_fee' => 30000, 'grand_total' => 1575000,
        ]);
        OrderItem::query()->create(['order_id' => $this->order->id, 'product_name' => 'Nhang trầm sạch', 'variant_name' => 'Hộp 100 cây', 'sku' => 'N100', 'price' => 185000, 'quantity' => 2, 'total' => 370000]);
        OrderItem::query()->create(['order_id' => $this->order->id, 'product_name' => 'Vòng tay trầm', 'sku' => 'V8', 'price' => 1345000, 'quantity' => 1, 'total' => 1345000]);
    }

    private function design(array $settings): void
    {
        app(EmailAppearance::class)->save($settings);
        $this->app->forgetInstance(EmailAppearance::class);
    }

    public function test_the_order_email_adds_up_and_shows_payment_and_delivery(): void
    {
        $html = (new OrderStatusMail($this->order))->render();

        $this->assertStringContainsString('Đã nhận đơn', $html);
        $this->assertStringContainsString('Cảm ơn bạn, Nguyễn Minh Anh! Shop đã nhận đơn.', $html);
        foreach (['1.715.000 ₫', '−50.000 ₫', '−120.000 ₫', '30.000 ₫', '1.575.000 ₫', 'Phí vận chuyển', 'Giao Hàng Nhanh', 'COD', 'Nhang trầm sạch', 'Hộp 100 cây', 'Gọi trước khi giao', '0909 123 456', '128 Nguyễn Thị Minh Khai, Q.3'] as $shown) {
            $this->assertStringContainsString($shown, $html, $shown);
        }
        $this->assertSame('Đã nhận đơn #DH-24103018 – cảm ơn bạn!', (new OrderStatusMail($this->order))->envelope()->subject);
    }

    public function test_each_status_gets_its_own_email(): void
    {
        $cases = [
            ['pending', null, 'order_placed'],
            ['processing', null, 'order_preparing'],
            ['processing', 'GHN9KX4T7L', 'order_shipping'],
            ['completed', 'GHN9KX4T7L', 'order_completed'],
            ['cancelled', null, 'order_cancelled'],
        ];
        foreach ($cases as [$status, $tracking, $email]) {
            $this->order->forceFill(['status' => $status, 'tracking_number' => $tracking]);
            $this->assertSame($email, OrderEmailData::emailFor($this->order), $status);
        }

        $html = (new OrderStatusMail($this->order->forceFill(['status' => 'processing', 'tracking_number' => 'GHN9KX4T7L'])))->render();
        $this->assertStringContainsString('Đang giao hàng', $html);
        $this->assertStringContainsString('https://donhang.ghn.vn/?order_code=GHN9KX4T7L', $html);

        $paid = $this->order->forceFill(['status' => 'cancelled', 'payment_status' => 'paid']);
        $this->assertStringContainsString('Shop sẽ liên hệ để hoàn tiền cho bạn.', (new OrderStatusMail($paid))->render());
    }

    public function test_the_shop_sets_colour_layout_footer_and_its_own_words(): void
    {
        $this->design([
            'layout' => 'banner', 'accent' => '#B45309', 'logo_align' => 'left',
            'signature' => "Trân trọng,\nĐội ngũ Phú Quý",
            'socials' => ['facebook' => 'https://facebook.com/phuquy', 'zalo' => 'http://zalo.me/plain', 'tiktok' => 'javascript:alert(1)'],
            'return_policy' => 'Đổi trả trong 7 ngày nếu hàng lỗi.',
            'emails' => ['order_placed' => ['subject' => 'Phú Quý nhận đơn {ma_don}', 'heading' => 'Chào {ten_khach} <b>vip</b>', 'intro' => '']],
        ]);

        $mail = new OrderStatusMail($this->order);
        $html = $mail->render();

        $this->assertSame('Phú Quý nhận đơn DH-24103018', $mail->envelope()->subject);
        $this->assertStringContainsString('Chào Nguyễn Minh Anh &lt;b&gt;vip&lt;/b&gt;', $html, 'the shop\'s words are text, never HTML');
        $this->assertStringContainsString('Shop sẽ liên hệ xác nhận', $html, 'an empty field keeps the default');
        $this->assertStringContainsString('#b45309', $html);
        $this->assertStringContainsString('Trân trọng,<br />', $html);
        $this->assertStringContainsString('https://facebook.com/phuquy', $html);
        $this->assertStringNotContainsString('zalo.me/plain', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $done = (new OrderStatusMail($this->order->forceFill(['status' => 'completed'])))->render();
        $this->assertStringContainsString('Đổi trả trong 7 ngày nếu hàng lỗi.', $done);
    }

    public function test_a_logo_on_this_server_is_embedded_even_without_the_public_storage_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo-phu-quy.png', 'png-bytes');
        $this->design(['logo' => 'storage/settings/logo-phu-quy.png']);
        config(['mail.default' => 'array']);

        Mail::to('anh@example.test')->sendNow(new OrderStatusMail($this->order));

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertMatchesRegularExpression('/<img src="cid:[^"]+" alt="Trầm Hương Phú Quý"/u', (string) $sent->getHtmlBody());
        $this->assertSame([['logo-phu-quy.png', 'inline']], array_map(fn ($part) => [$part->getFilename(), $part->getDisposition()], $sent->getAttachments()));
    }

    public function test_the_shops_words_are_for_its_default_language_only(): void
    {
        $this->design(['emails' => ['order_placed' => ['subject' => 'Phú Quý nhận đơn {ma_don}']]]);

        $english = $this->order->forceFill(['locale' => 'en']);
        Mail::fake();
        Mail::to('a@example.test')->locale('en')->send(new OrderStatusMail($english));

        Mail::assertSent(OrderStatusMail::class, function (OrderStatusMail $mail) {
            return $mail->locale === 'en';
        });
        $this->app->setLocale('en');
        $this->assertSame('We received order #DH-24103018 – thank you!', (new OrderStatusMail($english))->envelope()->subject);
    }

    public function test_a_switched_off_email_is_not_sent(): void
    {
        Mail::fake();
        $this->design(['emails' => ['order_placed' => ['enabled' => false]]]);

        (new SendOrderStatusEmail($this->order->id, 'anh@example.test'))->handle();
        Mail::assertNothingSent();

        $this->order->update(['status' => 'processing']);
        (new SendOrderStatusEmail($this->order->id, 'anh@example.test'))->handle();
        Mail::assertSent(OrderStatusMail::class, fn (OrderStatusMail $mail) => $mail->email === 'order_preparing');

        // A password reset cannot be switched off.
        $this->design(['emails' => ['reset_password' => ['enabled' => false]]]);
        $this->assertTrue(app(EmailAppearance::class)->enabled('reset_password'));
    }

    public function test_the_owner_gets_a_new_order_email_of_their_own(): void
    {
        Mail::fake();
        ProjectSetting::query()->create(['setting_key' => 'notification_settings', 'setting_value' => ['smtp' => ['enabled' => true, 'owner_email' => 'chu@phuquy.test']]]);
        Cache::forget(MailSettings::CACHE_KEY);

        NotificationHelper::deliverNewOrderNotification($this->order);

        $sent = Mail::sent(OwnerNewOrderMail::class)->first();
        $this->assertNotNull($sent);
        $this->assertTrue($sent->hasTo('chu@phuquy.test'));
        $this->assertSame('Đơn mới #DH-24103018 · 1.575.000 ₫', $sent->envelope()->subject);
        $html = $sent->render();
        $this->assertStringContainsString('tel:0938456712', $html);
        $this->assertStringContainsString('/admin/orders/'.$this->order->id, $html);
        Mail::assertNotSent(OrderStatusMail::class);

        Mail::fake();
        $this->design(['emails' => ['owner_new_order' => ['enabled' => false]]]);
        NotificationHelper::deliverNewOrderNotification($this->order);
        Mail::assertNothingSent();
    }

    public function test_a_contact_message_answers_the_customer_and_can_be_switched_off(): void
    {
        $mail = new ContactInquiryMail(['name' => 'Trần Quốc Bảo', 'phone' => '0912 884 301', 'email' => 'bao@example.test', 'message' => "Cần 20 vòng tay\nin logo"], ['product' => 'Vòng tay trầm']);

        $this->assertSame('Liên hệ mới: Trần Quốc Bảo', $mail->envelope()->subject);
        $this->assertSame('bao@example.test', $mail->envelope()->replyTo[0]->address);
        $html = $mail->render();
        $this->assertStringContainsString('Cần 20 vòng tay<br />', $html);
        $this->assertStringContainsString('tel:0912884301', $html);
        $this->assertStringContainsString('Vòng tay trầm', $html);

        Mail::fake();
        $this->design(['emails' => ['contact' => ['enabled' => false]]]);
        $this->postJson('/api/public/contact', ['name' => 'Bảo', 'phone' => '0912884301', 'message' => 'Xin báo giá sỉ'])->assertOk();
        $this->assertSame(1, ContactSubmission::query()->count(), 'still saved for the admin');
        Mail::assertNothingSent();
    }

    public function test_the_password_reset_and_settings_test_use_the_design(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);
        $admin = User::factory()->create(['email' => 'quanly@phuquy.test', 'role_id' => $role->id]);

        $reset = (new QueuedResetPassword('token-123'))->toMail($admin);
        $this->assertSame('Đặt lại mật khẩu tài khoản Trầm Hương Phú Quý', $reset->subject);
        $html = (string) $reset->render();
        $this->assertStringContainsString('token-123', $html);
        $this->assertStringContainsString('quanly@phuquy.test', $html);
        $this->assertStringContainsString('60 phút', $html);
        $this->assertStringNotContainsString('Laravel', $html);

        $test = new MailSettingsTestMail('Trầm Hương Phú Quý', 'shop@gmail.test', 'Phú Quý', [
            'via' => 'own_smtp', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'to' => ['chu@phuquy.test'],
        ]);
        $html = $test->render();
        $this->assertSame('[Trầm Hương Phú Quý] Email thử cấu hình gửi thư', $test->envelope()->subject);
        foreach (['SMTP riêng của shop', 'smtp.gmail.com : 587', 'TLS', 'shop@gmail.test', 'chu@phuquy.test'] as $shown) {
            $this->assertStringContainsString($shown, $html, $shown);
        }
    }

    public function test_the_font_is_the_shops_choice_ending_in_system_fonts(): void
    {
        $html = (new OrderStatusMail($this->order))->render();
        $this->assertStringContainsString('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600&amp;display=swap', $html);
        $this->assertStringContainsString("'Be Vietnam Pro',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto", $html);
        $this->assertDoesNotMatchRegularExpression('/font:(700|800) |font-weight:(700|800)/', $html, 'nothing heavier than semi-bold');

        $this->design(['font' => 'nunito_sans']);
        $this->assertStringContainsString("'Nunito Sans',-apple-system", (new OrderStatusMail($this->order))->render());

        // The device font: no web font to load (Gmail and Outlook would ignore it anyway).
        $this->design(['font' => 'system']);
        $html = (new OrderStatusMail($this->order))->render();
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('font-family: -apple-system,BlinkMacSystemFont', $html);

        $this->assertSame(EmailAppearance::DEFAULT_FONT, app(EmailAppearance::class)->sanitize(['font' => 'comic_sans'])['font']);
    }

    public function test_bad_settings_are_cleaned_before_they_are_stored(): void
    {
        $clean = app(EmailAppearance::class)->sanitize([
            'layout' => 'neon', 'accent' => 'red', 'logo_align' => 'right',
            'logo' => 'http://localhost/storage/settings/logo.png',
            'emails' => ['order_placed' => ['subject' => str_repeat('a', 400)], 'unknown' => ['subject' => 'x']],
        ]);

        $this->assertSame('classic', $clean['layout']);
        $this->assertSame(EmailAppearance::DEFAULT_ACCENT, $clean['accent']);
        $this->assertSame('center', $clean['logo_align']);
        $this->assertSame('/storage/settings/logo.png', $clean['logo'], 'stored as a portable path');
        $this->assertSame(150, mb_strlen($clean['emails']['order_placed']['subject']));
        $this->assertArrayNotHasKey('unknown', $clean['emails']);
        $this->assertSame(array_keys(EmailAppearance::EMAILS), array_keys($clean['emails']));
    }

    public function test_every_layout_renders_every_email(): void
    {
        foreach (EmailAppearance::LAYOUTS as $layout) {
            $this->design(['layout' => $layout, 'banner' => $layout === 'banner' ? 'https://cdn.example.test/banner.jpg' : null]);
            foreach (array_keys(OrderEmailData::STEPS) as $email) {
                $this->assertStringContainsString('DH-24103018', (new OrderStatusMail($this->order, $email))->render(), "{$layout} {$email}");
            }
            $this->assertStringContainsString('1.575.000', (new OwnerNewOrderMail($this->order))->render(), $layout);
            if ($layout === 'banner') {
                $this->assertStringContainsString('https://cdn.example.test/banner.jpg', (new OwnerNewOrderMail($this->order))->render());
            }
        }
    }
}
