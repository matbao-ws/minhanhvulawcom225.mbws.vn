<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Banner;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_view_and_filter_activity_logs(): void
    {
        $role = Role::query()->create(['name' => 'Auditor', 'permissions' => ['activity_logs.view']]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $subject = Role::query()->create(['name' => 'Sales', 'permissions' => []]);

        $this->actingAs($user);
        ActivityLogger::log('updated', $subject, 'Cập nhật vai trò', [
            'smtp_password' => 'must-not-be-stored',
            'cloudinary_url' => 'cloudinary://key:must-not-leak@cloud',
            'label' => 'safe',
        ]);

        $log = AdminActivityLog::query()->firstOrFail();
        $this->assertSame('[REDACTED]', $log->changes['smtp_password']);
        $this->assertSame('[REDACTED]', $log->changes['cloudinary_url']);
        $this->assertSame('safe', $log->changes['label']);

        $this->get('/vi/admin/activity-logs?action=updated')
            ->assertOk()
            ->assertSee('Cập nhật vai trò')
            ->assertDontSee('must-not-be-stored');
    }

    public function test_admin_without_audit_permission_cannot_view_activity_logs(): void
    {
        $role = Role::query()->create(['name' => 'Staff', 'permissions' => ['orders.view', 'orders.create', 'orders.update']]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/vi/admin/activity-logs')->assertForbidden();
    }

    public function test_regular_admin_cannot_view_or_filter_superadmin_activity_logs(): void
    {
        $superadminRole = Role::query()->create([
            'name' => 'Superadmin',
            'is_system' => true,
            'is_superadmin' => true,
            'permissions' => ['*'],
        ]);
        $auditorRole = Role::query()->create(['name' => 'Auditor', 'permissions' => ['activity_logs.view']]);
        $superadmin = User::factory()->create(['role_id' => $superadminRole->id]);
        $auditor = User::factory()->create(['role_id' => $auditorRole->id]);

        AdminActivityLog::query()->create([
            'user_id' => $superadmin->id,
            'action' => 'superadmin_only_action',
            'description' => 'Superadmin-only audit event',
            'created_at' => now(),
        ]);
        AdminActivityLog::query()->create([
            'user_id' => $auditor->id,
            'action' => 'updated',
            'description' => 'Auditor audit event',
            'created_at' => now(),
        ]);

        $this->actingAs($auditor)
            ->get('/vi/admin/activity-logs')
            ->assertOk()
            ->assertSee('Auditor audit event')
            ->assertDontSee('Superadmin-only audit event')
            ->assertDontSee($superadmin->email)
            ->assertDontSee('Superadmin Only Action');

        $this->get('/vi/admin/activity-logs?user_id='.$superadmin->id)
            ->assertOk()
            ->assertDontSee('Superadmin-only audit event');

        $this->actingAs($superadmin)
            ->get('/vi/admin/activity-logs')
            ->assertOk()
            ->assertSee('Superadmin-only audit event')
            ->assertSee('Auditor audit event');
    }

    public function test_bulk_activity_log_is_presented_in_plain_language(): void
    {
        $role = Role::query()->create(['name' => 'Auditor', 'permissions' => ['activity_logs.view']]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user);
        ActivityLogger::log('bulk_deleted', null, 'Xóa hàng loạt banner', [
            'model' => Banner::class,
            'ids' => [6, 7],
            'count' => 2,
        ]);

        $this->get('/vi/admin/activity-logs')
            ->assertOk()
            ->assertSee('Xóa nhiều')
            ->assertSee('Banner')
            ->assertSee('Đã xóa 2 banner đã chọn.')
            ->assertSee('Số lượng đã xóa:')
            ->assertDontSee('App\\Models\\Banner')
            ->assertDontSee('&quot;ids&quot;', false);
    }

    public function test_superadmin_sees_selection_and_delete_controls(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin);
        ActivityLogger::log('updated', null, 'Một hoạt động');

        $this->get('/vi/admin/activity-logs')
            ->assertOk()
            ->assertSee('data-activity-select-all', false)
            ->assertSee(route('admin.activity-logs.bulk-destroy'), false)
            ->assertSee('Xóa tất cả (1)');
    }

    public function test_superadmin_can_delete_selected_entries(): void
    {
        $this->actingAs($this->superadmin());
        ActivityLogger::log('updated', null, 'Giữ lại');
        ActivityLogger::log('updated', null, 'Xóa 1');
        ActivityLogger::log('updated', null, 'Xóa 2');
        $ids = AdminActivityLog::query()->where('description', 'like', 'Xóa %')->pluck('id')->all();

        $this->delete('/vi/admin/activity-logs/bulk', ['ids' => $ids])
            ->assertRedirect('/vi/admin/activity-logs')
            ->assertSessionHas('success', 'Đã xóa 2 bản ghi nhật ký.');

        $this->assertDatabaseHas('admin_activity_logs', ['description' => 'Giữ lại']);
        $this->assertDatabaseMissing('admin_activity_logs', ['description' => 'Xóa 1']);
        // Deleting does not write a new entry about itself.
        $this->assertSame(1, AdminActivityLog::query()->count());
    }

    public function test_delete_all_removes_everything_when_no_filter_is_set(): void
    {
        $this->actingAs($this->superadmin());
        ActivityLogger::log('updated', null, 'A');
        ActivityLogger::log('created', null, 'B');

        $this->delete('/vi/admin/activity-logs')->assertRedirect('/vi/admin/activity-logs');

        $this->assertSame(0, AdminActivityLog::query()->count());
    }

    public function test_delete_all_respects_the_filters_on_screen(): void
    {
        $this->actingAs($this->superadmin());
        ActivityLogger::log('updated', null, 'Cập nhật');
        ActivityLogger::log('created', null, 'Tạo mới');

        $this->delete('/vi/admin/activity-logs', ['action' => 'updated'])
            ->assertRedirect('/vi/admin/activity-logs?action=updated');

        $this->assertDatabaseMissing('admin_activity_logs', ['description' => 'Cập nhật']);
        $this->assertDatabaseHas('admin_activity_logs', ['description' => 'Tạo mới']);
    }

    public function test_only_superadmin_may_delete_activity_logs(): void
    {
        $role = Role::query()->create(['name' => 'Auditor', 'permissions' => ['activity_logs.view']]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($admin);
        ActivityLogger::log('updated', null, 'Không được xóa');
        $id = AdminActivityLog::query()->value('id');

        $this->get('/vi/admin/activity-logs')
            ->assertOk()
            ->assertDontSee('data-activity-select-all', false)
            ->assertDontSee('Xóa tất cả');

        $this->delete('/vi/admin/activity-logs/bulk', ['ids' => [$id]])->assertForbidden();
        $this->delete('/vi/admin/activity-logs')->assertForbidden();
        $this->assertDatabaseHas('admin_activity_logs', ['description' => 'Không được xóa']);
    }

    private function superadmin(): User
    {
        $role = Role::query()->create(['name' => 'Superadmin', 'permissions' => ['*'], 'is_system' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
