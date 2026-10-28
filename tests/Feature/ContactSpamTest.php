<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Models\ContactSubmission;
use App\Models\ProjectSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Spam on the public contact form: answered like a real inquiry, kept flagged, never mailed. */
class ContactSpamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_a_real_inquiry_is_mailed_and_records_the_ip(): void
    {
        $this->send(['phone' => '+84 (90) 123-4567'])->assertOk()->assertJsonPath('success', true);

        $submission = ContactSubmission::query()->sole();
        $this->assertFalse($submission->is_spam);
        $this->assertSame('127.0.0.1', $submission->ip);
        Mail::assertSent(ContactInquiryMail::class);
    }

    public function test_the_inquiry_goes_to_the_owner_email_set_in_the_admin(): void
    {
        config(['mail.seller' => 'fallback@example.com']);
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => 'notification_settings'],
            ['setting_value' => ['smtp' => ['owner_email' => 'chu-shop@example.com']]],
        );
        MailSettings::flush();

        $this->send()->assertOk();

        Mail::assertSent(ContactInquiryMail::class, fn ($mail) => $mail->hasTo('chu-shop@example.com') && ! $mail->hasTo('fallback@example.com'));
    }

    public function test_the_inquiry_goes_to_every_owner_email(): void
    {
        ProjectSetting::query()->updateOrCreate(
            ['setting_key' => 'notification_settings'],
            ['setting_value' => ['smtp' => ['owner_email' => 'chu-shop@example.com, ke-toan@example.com']]],
        );
        MailSettings::flush();

        $this->send()->assertOk();

        Mail::assertSent(ContactInquiryMail::class, fn ($mail) => $mail->hasTo('chu-shop@example.com') && $mail->hasTo('ke-toan@example.com'));
    }

    #[DataProvider('spam')]
    public function test_spam_gets_the_same_answer_but_is_flagged_and_not_mailed(array $fields, string $reason): void
    {
        // Providers run when the suite starts: the time is filled in now.
        if (($fields['_ts'] ?? null) === 'now-1s-in-ms') {
            $fields['_ts'] = (string) ((time() - 1) * 1000);
        }

        $this->send($fields)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Yêu cầu của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất!');

        $submission = ContactSubmission::query()->sole();
        $this->assertTrue($submission->is_spam);
        $this->assertSame($reason, $submission->spam_reason);
        $this->assertNull($submission->meta, 'the guard fields are not stored as extra info');
        Mail::assertNothingSent();
    }

    public static function spam(): array
    {
        return [
            'hidden field filled' => [['_hp' => 'http://bot.example'], 'honeypot'],
            'sent 1 second after the form showed (ms)' => [['_ts' => 'now-1s-in-ms'], 'too_fast'],
            'link in the name' => [['name' => 'Cheap SEO https://spam.example'], 'link_in_name'],
            'html link' => [['message' => 'Hello <a href="https://x.example">click</a>'], 'html_link'],
            'bbcode link' => [['message' => 'Hi [url=https://x.example]x[/url]'], 'html_link'],
            'three links' => [['message' => 'https://a.example https://b.example www.c.example'], 'too_many_links'],
            'blocked word' => [['message' => 'Nhà cái uy tín, tặng 100k'], 'blocked_word'],
        ];
    }

    public function test_a_form_shown_long_enough_ago_and_an_empty_hidden_field_pass(): void
    {
        $this->send(['_hp' => '', '_ts' => (string) (time() - 30)])->assertOk();

        $this->assertFalse(ContactSubmission::query()->sole()->is_spam);
        Mail::assertSent(ContactInquiryMail::class);
    }

    public function test_the_same_inquiry_twice_in_a_day_is_kept_once(): void
    {
        $this->send()->assertOk();
        $this->send()->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('contact_submissions', 1);
        Mail::assertSent(ContactInquiryMail::class, 1);

        $this->travel(25)->hours();
        $this->send()->assertOk();
        $this->assertDatabaseCount('contact_submissions', 2);
    }

    public function test_websites_add_their_own_blocked_words(): void
    {
        config(['contact.blocked_words' => ['bán follow']]);

        $this->send(['message' => 'Dịch vụ BÁN FOLLOW giá rẻ'])->assertOk();

        $this->assertSame('blocked_word', ContactSubmission::query()->sole()->spam_reason);
    }

    public function test_a_phone_needs_8_to_15_digits(): void
    {
        foreach (['12345', 'abc12345678', '0901234567890123456', '090-ABC-1234'] as $phone) {
            $this->send(['phone' => $phone])->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_an_ip_is_limited_per_minute_and_per_day(): void
    {
        config(['contact.per_minute' => 2, 'contact.per_day' => 3]);
        RateLimiter::clear('contact-minute:127.0.0.1');

        $this->send(['message' => 'a'])->assertOk();
        $this->send(['message' => 'b'])->assertOk();
        $this->send(['message' => 'c'])->assertStatus(429);

        $this->travel(2)->minutes();
        $this->send(['message' => 'd'])->assertOk();
        $this->send(['message' => 'e'])->assertStatus(429);

        $this->travel(2)->minutes();
        $this->send(['message' => 'f'])->assertStatus(429, 'daily ceiling reached');
    }

    public function test_spam_stays_out_of_the_inbox_and_can_be_released(): void
    {
        $this->send(['message' => 'Real customer']);
        $this->send(['message' => 'casino bonus', 'phone' => '0911111111']);
        $spam = ContactSubmission::query()->where('is_spam', true)->sole();

        $role = Role::query()->create(['name' => 'Contacts', 'permissions' => ['contacts.view', 'contacts.update', 'contacts.delete']]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->get('/vi/admin/contact-submissions')
            ->assertOk()->assertSee('Real customer')->assertDontSee('casino bonus');
        $this->actingAs($admin)->get('/vi/admin/contact-submissions?box=spam')
            ->assertOk()->assertSee('casino bonus')->assertSee('Từ khoá spam')->assertDontSee('Real customer');

        $this->actingAs($admin)->patch('/vi/admin/contact-submissions/'.$spam->id.'/not-spam')->assertRedirect();
        $this->assertFalse($spam->fresh()->is_spam);
        $this->actingAs($admin)->get('/vi/admin/contact-submissions')->assertSee('casino bonus');
    }

    private function send(array $fields = []): TestResponse
    {
        return $this->postJson('/api/public/contact', array_merge([
            'name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'email' => 'a@example.com',
            'message' => 'Tôi cần báo giá ống nhựa.',
        ], $fields));
    }
}
