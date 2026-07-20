<?php

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_install_seeds_foundation_data_without_demo_data(): void
    {
        $this->artisan('core:install', [
            '--no-interaction' => true,
            '--admin-name' => 'Core Admin',
            '--admin-email' => 'core-admin@example.com',
            '--admin-password' => 'a-strong-install-password',
        ])->assertExitCode(0);

        $admin = User::query()->where('email', 'core-admin@example.com')->firstOrFail();

        $this->assertSame('Core Admin', $admin->name);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue(Hash::check('a-strong-install-password', $admin->password));
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertTrue(FeatureSetting::query()->where('feature_code', 'catalog')->value('is_enabled'));
    }

    public function test_core_check_passes_after_the_foundation_is_installed(): void
    {
        $this->artisan('core:install', [
            '--no-interaction' => true,
            '--admin-name' => 'Core Admin',
            '--admin-email' => 'core-admin@example.com',
            '--admin-password' => 'a-strong-install-password',
        ])->assertExitCode(0);

        $this->artisan('core:check')->assertExitCode(0);
    }
}
