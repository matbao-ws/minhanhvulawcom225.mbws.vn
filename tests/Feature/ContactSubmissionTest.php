<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_submission_is_persisted_with_extra_fields_in_meta(): void
    {
        Mail::fake();

        $this->postJson('/api/public/contact', [
            'name' => 'Nguyễn Văn A',
            'phone' => '0900000000',
            'email' => 'a@example.com',
            'message' => 'Tôi cần báo giá.',
            'company' => 'Acme Corp',
            'budget' => '10-20 triệu',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('contact_submissions', 1);
        $submission = ContactSubmission::query()->firstOrFail();
        $this->assertSame('Nguyễn Văn A', $submission->name);
        $this->assertFalse($submission->is_read);
        $this->assertSame(['company' => 'Acme Corp', 'budget' => '10-20 triệu'], $submission->meta);
    }

    /** A form produces a few short flat values; nothing else is worth storing. */
    public function test_extra_fields_are_kept_only_in_the_shape_a_form_produces(): void
    {
        Mail::fake();

        $this->postJson('/api/public/contact', [
            'name' => 'Nguyễn Văn C',
            'phone' => '0900000001',
            'message' => 'Tôi cần báo giá.',
            'company' => str_repeat('a', 5000),
            'nested' => ['deep' => ['payload' => 'x']],
            'bad key!' => 'x',
            ...collect(range(1, 40))->mapWithKeys(fn (int $i) => ["field_{$i}" => 'v'])->all(),
        ])->assertOk();

        $meta = ContactSubmission::query()->firstOrFail()->meta;
        $this->assertCount(20, $meta);
        $this->assertSame(1000, mb_strlen($meta['company']));
        $this->assertArrayNotHasKey('nested', $meta);
        $this->assertArrayNotHasKey('bad key!', $meta);
    }

    public function test_public_contact_submission_without_extra_fields_has_null_meta(): void
    {
        Mail::fake();

        $this->postJson('/api/public/contact', [
            'name' => 'Trần Thị B',
            'phone' => '0911111111',
            'message' => 'Xin chào.',
        ])->assertOk();

        $submission = ContactSubmission::query()->firstOrFail();
        $this->assertNull($submission->meta);
    }

    public function test_guests_and_unauthorized_admins_cannot_access_contact_submissions(): void
    {
        $this->get('/vi/admin/contact-submissions')->assertRedirect('/vi/admin/login');

        $customer = User::factory()->create(['role_id' => null]);
        $this->actingAs($customer)->get('/vi/admin/contact-submissions')->assertForbidden();
    }

    public function test_empty_submission_list_renders_the_shared_empty_state(): void
    {
        $role = Role::query()->create([
            'name' => 'Contact manager',
            'permissions' => ['contacts.view', 'contacts.update', 'contacts.delete'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)
            ->get('/vi/admin/contact-submissions')
            ->assertOk()
            ->assertSee('admin-assets/images/icons/empty-lienhe.png', false)
            ->assertSee('width="240"', false)
            ->assertSeeText('Hiện tại chưa có liên hệ nào');
    }

    public function test_authorized_admin_can_view_toggle_and_delete_a_submission(): void
    {
        $role = Role::query()->create([
            'name' => 'Contact manager',
            'permissions' => ['contacts.view', 'contacts.update', 'contacts.delete'],
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);

        $submission = ContactSubmission::query()->create([
            'name' => 'Lê Văn C',
            'phone' => '0922222222',
            'message' => 'Cần hỗ trợ.',
        ]);

        $this->actingAs($admin)
            ->get('/vi/admin/contact-submissions')
            ->assertOk()
            ->assertSee('Lê Văn C')
            ->assertSee('data-read-url="', false)
            ->assertSee('class="js-delete-form"', false);

        $this->actingAs($admin)
            ->patchJson("/vi/admin/contact-submissions/{$submission->id}/toggle-read")
            ->assertOk()
            ->assertJsonPath('is_read', true);
        $this->assertTrue($submission->fresh()->is_read);

        $this->actingAs($admin)
            ->delete("/vi/admin/contact-submissions/{$submission->id}")
            ->assertRedirect();
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_the_inbox_shows_the_whole_message_with_ways_to_reply(): void
    {
        $role = Role::query()->create(['name' => 'Contact reader', 'permissions' => ['contacts.view']]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $long = str_repeat('Cần tư vấn ống HDPE phi 90 cho công trình. ', 10).'ĐOẠN CUỐI';

        ContactSubmission::query()->create([
            'name' => 'Phạm D',
            'phone' => '+84 901 234 567',
            'email' => 'd@example.com',
            'message' => $long,
            'meta' => ['company' => 'Công ty <b>ABC</b>'],
        ]);

        $this->actingAs($admin)->get('/vi/admin/contact-submissions')->assertOk()
            // The list shows a preview; the whole message is on the page for the reading pane.
            ->assertSee('ĐOẠN CUỐI')
            ->assertSee('href="tel:84901234567"', false)
            ->assertSee('href="https://zalo.me/0901234567"', false)
            ->assertSee('href="mailto:d@example.com?subject=', false)
            // Extra fields are labelled values, escaped, not raw JSON.
            ->assertSee('Company')
            ->assertSee('Công ty &lt;b&gt;ABC&lt;/b&gt;', false)
            // Without contacts.update / contacts.delete: no mark-read call, no buttons that would 403.
            ->assertDontSee('data-read-url="', false)
            ->assertDontSee('data-contact-toggle-read>', false)
            ->assertDontSee('class="js-delete-form"', false);
    }
}
