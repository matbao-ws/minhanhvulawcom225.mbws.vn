<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
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
        $role = Role::query()->create(['name' => 'Auditor', 'permissions' => ['view_audit_log']]);
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
        $role = Role::query()->create(['name' => 'Staff', 'permissions' => ['manage_orders']]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/vi/admin/activity-logs')->assertForbidden();
    }
}
