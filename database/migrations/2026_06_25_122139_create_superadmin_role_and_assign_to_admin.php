<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Superadmin role if it doesn't exist
        $superadminRole = Role::query()->firstOrCreate(
            ['name' => 'Superadmin'],
            ['permissions' => ['*']]
        );

        // 2. Assign default admin user to Superadmin role
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $user = User::query()->where('email', $adminEmail)->first();
        if ($user) {
            $user->update(['role_id' => $superadminRole->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the user to the Admin role if down is run
        $adminRole = Role::query()->where('name', 'Admin')->first();
        if ($adminRole) {
            $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
            $user = User::query()->where('email', $adminEmail)->first();
            if ($user) {
                $user->update(['role_id' => $adminRole->id]);
            }
        }
    }
};
