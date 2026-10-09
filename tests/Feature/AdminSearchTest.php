<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header search answers only with what the searcher may open.
 */
class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The users loop used to reuse `$user`, so every check after it ran against
     * the last account the search matched instead of the searcher.
     */
    public function test_permissions_are_checked_against_the_searcher_not_the_matched_accounts(): void
    {
        $superRole = Role::query()->create(['name' => 'Root', 'permissions' => ['*'], 'is_superadmin' => true, 'is_system' => true]);
        User::factory()->create(['role_id' => $superRole->id, 'name' => 'Zeta Boss']);
        $staffRole = Role::query()->create(['name' => 'Staff', 'permissions' => ['users.view']]);
        User::factory()->create(['role_id' => $staffRole->id, 'name' => 'Zeta Helper']);
        Post::query()->create(['title' => ['vi' => 'Zeta bí mật'], 'slug' => 'zeta-bi-mat', 'content' => ['vi' => '<p>x</p>']]);

        $searcher = User::factory()->create(['role_id' => Role::query()->create(['name' => 'HR', 'permissions' => ['users.view']])->id]);

        $response = $this->actingAs($searcher)->getJson('/vi/admin/search?q=Zeta')->assertOk();

        $titles = collect($response->json('results'))->flatMap(fn (array $group) => array_column($group['items'], 'title'));
        $this->assertContains('Zeta Helper', $titles);
        $this->assertNotContains('Zeta Boss', $titles, 'privileged accounts stay hidden from non-superadmins');
        $this->assertNotContains('Zeta bí mật', $titles, 'no posts without posts.view');
    }

    public function test_customers_are_not_listed_as_users(): void
    {
        User::factory()->create(['role_id' => null, 'name' => 'Khách Zeta', 'email' => 'zeta-customer@example.com']);
        $searcher = User::factory()->create(['role_id' => Role::query()->create(['name' => 'HR', 'permissions' => ['users.view']])->id]);

        $response = $this->actingAs($searcher)->getJson('/vi/admin/search?q=zeta-customer')->assertOk();

        $this->assertSame([], $response->json('results'));
    }

    public function test_a_non_string_or_oversized_query_returns_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/vi/admin/search?q[]=x')->assertOk()->assertExactJson(['results' => []]);
        $this->getJson('/vi/admin/search?q='.str_repeat('a', 101))->assertOk()->assertExactJson(['results' => []]);
    }
}
