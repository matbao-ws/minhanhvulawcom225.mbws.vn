<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailAppearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Quản trị › Giao diện email: the page, saving the design, and the live preview. */
class EmailDesignAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        ProjectSetting::query()->create(['setting_key' => 'shop_name', 'setting_value' => 'Trầm Hương Phú Quý']);
        $this->editor = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Admin', 'permissions' => ['settings.view', 'settings.update']])->id]);
        $this->viewer = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Viewer', 'permissions' => ['settings.view']])->id]);
    }

    public function test_the_page_shows_layouts_colour_footer_each_email_and_the_preview(): void
    {
        $this->actingAs($this->editor)->get('/vi/admin/email-design')->assertOk()
            ->assertSee('Giao diện email')
            ->assertSee('Cổ điển')->assertSee('Tối giản')->assertSee('Ảnh bìa')
            ->assertSee('name="accent"', false)
            ->assertSee('name="socials[facebook]"', false)
            ->assertSee('name="emails[order_placed][subject]"', false)
            ->assertSee('placeholder="Đã nhận đơn #{ma_don} – cảm ơn bạn!"', false)
            ->assertSee('name="emails[owner_new_order][enabled]"', false)
            ->assertDontSee('name="emails[reset_password][enabled]"', false)
            ->assertSee('data-preview-frame', false)
            ->assertSee(route('admin.email-design.preview', ['locale' => 'vi']));

        $this->actingAs($this->editor)->get('/vi/admin/dashboard')->assertSee('/vi/admin/email-design');
    }

    public function test_saving_stores_a_clean_design(): void
    {
        $this->actingAs($this->editor)->post('/vi/admin/email-design', [
            'layout' => 'banner', 'accent' => '#B45309', 'logo_align' => 'left',
            'logo' => url('/storage/settings/email-logo.png'),
            'signature' => "Trân trọng,\nPhú Quý",
            'socials' => ['facebook' => 'https://facebook.com/phuquy', 'zalo' => ''],
            'emails' => [
                'order_placed' => ['enabled' => '1', 'subject' => 'Phú Quý nhận đơn {ma_don}'],
                'owner_new_order' => ['enabled' => '0'],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $saved = ProjectSetting::query()->where('setting_key', EmailAppearance::KEY)->value('setting_value');
        $this->assertSame('banner', $saved['layout']);
        $this->assertSame('#b45309', $saved['accent']);
        $this->assertSame('/storage/settings/email-logo.png', $saved['logo'], 'a portable path, never this host');
        $this->assertSame(['facebook' => 'https://facebook.com/phuquy'], $saved['socials']);
        $this->assertSame('Phú Quý nhận đơn {ma_don}', $saved['emails']['order_placed']['subject']);
        $this->assertFalse($saved['emails']['owner_new_order']['enabled']);
        $this->assertTrue($saved['emails']['order_shipping']['enabled']);
    }

    public function test_bad_input_is_refused(): void
    {
        $this->actingAs($this->editor)->from('/vi/admin/email-design')
            ->post('/vi/admin/email-design', ['accent' => 'red', 'layout' => 'neon', 'socials' => ['facebook' => 'http://plain.example']])
            ->assertSessionHasErrors(['accent', 'layout', 'socials.facebook']);

        $this->assertNull(ProjectSetting::query()->where('setting_key', EmailAppearance::KEY)->first());
    }

    public function test_viewing_needs_settings_view_and_saving_settings_update(): void
    {
        $this->actingAs($this->viewer)->get('/vi/admin/email-design')->assertOk()->assertDontSee('data-tour="form.save"', false);
        $this->actingAs($this->viewer)->post('/vi/admin/email-design', ['layout' => 'minimal'])->assertForbidden();

        $nobody = User::factory()->create(['role_id' => Role::query()->create(['name' => 'Nobody', 'permissions' => []])->id]);
        $this->actingAs($nobody)->get('/vi/admin/email-design')->assertForbidden();
    }

    public function test_the_preview_renders_every_email_from_the_unsaved_form(): void
    {
        foreach (array_keys(EmailAppearance::EMAILS) as $email) {
            $html = $this->actingAs($this->viewer)->post('/vi/admin/email-design/preview', [
                'email' => $email, 'accent' => '#7c3aed', 'layout' => 'minimal',
                'emails' => ['order_placed' => ['heading' => 'Chào {ten_khach} từ bản nháp']],
            ])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();

            $this->assertStringContainsString('#7c3aed', $html, $email);
            $this->assertStringContainsString('Trầm Hương Phú Quý', $html, $email);
        }

        $this->actingAs($this->viewer)->post('/vi/admin/email-design/preview', ['email' => 'order_placed', 'emails' => ['order_placed' => ['heading' => 'Chào {ten_khach} từ bản nháp']]])
            ->assertSee('Chào Nguyễn Minh Anh từ bản nháp');

        $this->assertNull(ProjectSetting::query()->where('setting_key', EmailAppearance::KEY)->first(), 'a preview stores nothing');
        $this->assertSame(0, Order::query()->count(), 'the sample order is never saved');
        $this->actingAs($this->viewer)->post('/vi/admin/email-design/preview', ['email' => 'nope'])->assertSessionHasErrors('email');
    }
}
