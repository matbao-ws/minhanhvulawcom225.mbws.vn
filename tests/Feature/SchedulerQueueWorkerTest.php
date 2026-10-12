<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Shared hosting has no queue worker: the scheduler drains the queue, or queued mail (password
 * reset, order status, owner notifications) never leaves the `jobs` table.
 */
class SchedulerQueueWorkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_works_the_queue_every_minute_unless_turned_off(): void
    {
        $worker = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'queue:work'));

        $this->assertNotNull($worker);
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertStringContainsString('--stop-when-empty', $worker->command);
        $this->assertStringContainsString('--max-time=50', $worker->command);

        config(['queue.default' => 'database', 'queue.scheduler_worker' => true]);
        $this->assertTrue($worker->filtersPass($this->app));

        config(['queue.scheduler_worker' => false]);
        $this->assertFalse($worker->filtersPass($this->app), 'off where a real worker runs');

        config(['queue.scheduler_worker' => true, 'queue.default' => 'sync']);
        $this->assertFalse($worker->filtersPass($this->app), 'nothing to work when mail is sent inline');
    }

    public function test_the_customer_reset_mail_is_queued_and_admins_get_none_from_the_api(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role_id' => null, 'email' => 'khach@example.com']);

        $this->postJson('/api/public/auth/forgot-password', ['email' => 'khach@example.com'])->assertOk();
        Notification::assertSentTo($customer, QueuedResetPassword::class);

        $role = Role::query()->create(['name' => 'Admin', 'permissions' => ['*']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'email' => 'admin@example.com']);
        $this->postJson('/api/public/auth/forgot-password', ['email' => 'admin@example.com'])->assertOk();
        Notification::assertNotSentTo($admin, QueuedResetPassword::class);
    }
}
