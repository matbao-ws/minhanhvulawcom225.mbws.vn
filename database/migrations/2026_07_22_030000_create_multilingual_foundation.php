<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 100);
            $table->string('native_name', 100);
            $table->string('regional', 24)->nullable();
            $table->string('flag_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_content_fallback')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('localized_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('sluggable_type', 120);
            $table->unsignedBigInteger('sluggable_id');
            $table->string('locale', 16);
            $table->string('slug');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['sluggable_type', 'locale', 'slug'], 'localized_slugs_lookup_unique');
            $table->index(['sluggable_type', 'sluggable_id', 'locale', 'is_current'], 'localized_slugs_current_index');
        });

        Schema::create('translation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('source_locale', 16);
            $table->string('target_locale', 16);
            $table->unsignedInteger('character_count')->default(0);
            $table->string('source_hash', 64);
            $table->string('status', 24);
            $table->string('error_code', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale', 16)->nullable()->after('email');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 16)->default('vi')->after('order_number')->index();
        });

        $now = now();
        DB::table('languages')->insert([
            [
                'code' => 'vi',
                'name' => 'Vietnamese',
                'native_name' => 'Tiếng Việt',
                'regional' => 'vi_VN',
                'flag_path' => 'admin-assets/images/flag/Flag_of_Vietnam.svg.png',
                'is_active' => true,
                'is_default' => true,
                'is_content_fallback' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'regional' => 'en_US',
                'flag_path' => 'admin-assets/images/flag/icon-flag-en.svg',
                'is_active' => true,
                'is_default' => false,
                'is_content_fallback' => false,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        if (Schema::hasTable('permissions')) {
            foreach ([
                ['code' => 'manage_languages', 'name' => 'Quản lý ngôn ngữ', 'group' => 'settings'],
                ['code' => 'translate_content', 'name' => 'Dịch nội dung tự động', 'group' => 'system'],
            ] as $permission) {
                DB::table('permissions')->updateOrInsert(
                    ['code' => $permission['code']],
                    [...$permission, 'updated_at' => $now, 'created_at' => $now],
                );
            }

            DB::table('roles')->where('is_system', false)->orderBy('id')->each(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true) ?: [];
                if (in_array('manage_products', $permissions, true) || in_array('manage_posts', $permissions, true)) {
                    $permissions[] = 'translate_content';
                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values(array_unique($permissions))),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_locale');
        });

        Schema::dropIfExists('translation_requests');
        Schema::dropIfExists('localized_slugs');
        Schema::dropIfExists('languages');

        if (Schema::hasTable('permissions')) {
            DB::table('roles')->orderBy('id')->each(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true) ?: [];
                $permissions = array_values(array_diff($permissions, ['manage_languages', 'translate_content']));
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions),
                    'updated_at' => now(),
                ]);
            });
            DB::table('permissions')->whereIn('code', ['manage_languages', 'translate_content'])->delete();
        }
    }
};
