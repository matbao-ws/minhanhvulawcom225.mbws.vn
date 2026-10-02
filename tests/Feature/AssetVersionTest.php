<?php

namespace Tests\Feature;

use App\Support\AssetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_admin_assets_carry_their_file_version(): void
    {
        $this->assertSame(
            asset('admin-assets/css/styles.css').'?v='.filemtime(public_path('admin-assets/css/styles.css')),
            AssetVersion::url('admin-assets/css/styles.css'),
        );
        $this->assertSame(asset('admin-assets/missing.css'), AssetVersion::url('admin-assets/missing.css'));

        $this->get('/vi/admin/login')->assertOk()->assertSee('admin-assets/css/styles.css?v=', false);
    }
}
