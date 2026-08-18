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
            ->assertSee('admin-assets/images/icons/emptycomment.png', false)
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
            ->assertSee('Lê Văn C');

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
}
