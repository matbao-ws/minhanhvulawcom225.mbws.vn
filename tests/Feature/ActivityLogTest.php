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
            'label' => 'safe',
        ]);

        $log = AdminActivityLog::query()->firstOrFail();
        $this->assertSame('[REDACTED]', $log->changes['smtp_password']);
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
}
