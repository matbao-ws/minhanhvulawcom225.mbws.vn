<?php

namespace Tests\Feature;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin lists always show their row count and page controls, from the first row on: the controls
 * stay disabled until there is a second page. Every list takes ?per_page.
 */
class AdminPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_a_list_on_one_page_still_shows_its_count_and_disabled_controls(): void
    {
        $total = Role::query()->count();

        $html = $this->actingAs($this->admin)->get('/vi/admin/roles')->assertOk()
            ->assertSee(__('admin.pagination.summary', ['from' => 1, 'to' => $total, 'total' => $total]))
            ->assertSee('name="per_page"', false)
            ->getContent();

        // One page: previous and next are there, and disabled.
        $this->assertSame(2, substr_count($html, '<li class="page-item disabled" aria-disabled="true">'));
        // The screen's default size (15) is the selected one.
        $this->assertMatchesRegularExpression('/<option value="15"\s+selected/', $html);
    }

    public function test_more_rows_than_a_page_get_working_links_and_per_page_is_honoured(): void
    {
        foreach (range(1, 20) as $i) {
            Role::query()->create(['name' => 'Vai trò '.$i, 'permissions' => []]);
        }
        $total = Role::query()->count();

        $this->actingAs($this->admin)->get('/vi/admin/roles')->assertOk()
            ->assertSee(__('admin.pagination.summary', ['from' => 1, 'to' => 15, 'total' => $total]))
            ->assertSee('rel="next"', false);

        $this->actingAs($this->admin)->get('/vi/admin/roles?per_page=25')->assertOk()
            ->assertSee(__('admin.pagination.summary', ['from' => 1, 'to' => min(25, $total), 'total' => $total]));
    }

    public function test_lists_built_outside_the_usual_controllers_honour_per_page_too(): void
    {
        foreach (range(1, 30) as $i) {
            Redirect::query()->create(['source_path' => '/cu-'.$i, 'target_url' => '/moi-'.$i, 'status_code' => 301, 'origin' => Redirect::ORIGIN_MANUAL, 'is_active' => true]);
            NotFoundLog::query()->create(['path' => '/hong-'.$i, 'hits' => $i, 'last_seen_at' => now()]);
        }

        $this->actingAs($this->admin);
        $this->assertSame(50, $this->get('/vi/admin/redirects?per_page=50')->assertOk()->viewData('redirectList')->perPage());
        $this->assertSame(50, $this->get('/vi/admin/redirects?tab=not-found&per_page=50')->assertOk()->viewData('notFoundList')->perPage());
        $this->assertSame(50, $this->get('/vi/admin/notifications?per_page=50')->assertOk()->viewData('notifications')->perPage());
        // Outside the allow-list: the screen default.
        $this->assertSame(25, $this->get('/vi/admin/redirects?per_page=5000')->assertOk()->viewData('redirectList')->perPage());
    }
}
