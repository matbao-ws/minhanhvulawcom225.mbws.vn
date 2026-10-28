<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → Đối tác: partner logos and their groups, managed from the admin instead of inline
 * editing on the storefront.
 */
class PartnerAdminTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['partners.view', 'partners.create', 'partners.update', 'partners.delete', 'media.view'];

    private PartnerGroup $customers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->customers = PartnerGroup::query()->create(['key' => 'customers', 'name' => 'Khách hàng tiêu biểu', 'sort_order' => 0]);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/vi/admin/partners')->assertRedirect('/vi/admin/login');
    }

    public function test_an_admin_without_the_permission_is_refused(): void
    {
        $admin = $this->adminWith(['banners.view']);

        $this->actingAs($admin)->get('/vi/admin/partners')->assertForbidden();
        $this->actingAs($admin)->get('/vi/admin/partner-groups')->assertForbidden();
    }

    public function test_view_only_admin_sees_the_list_but_cannot_write(): void
    {
        $admin = $this->adminWith(['partners.view']);
        $partner = Partner::factory()->for($this->customers, 'group')->create(['name' => 'Panasonic']);

        $this->actingAs($admin)->get('/vi/admin/partners')
            ->assertOk()
            ->assertSee('Panasonic')
            ->assertDontSee(route('admin.partners.create', ['locale' => 'vi']), false);

        $this->actingAs($admin)->get('/vi/admin/partners/create')->assertForbidden();
        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload())->assertForbidden();
        $this->actingAs($admin)->put("/vi/admin/partners/{$partner->id}", $this->payload())->assertForbidden();
        $this->actingAs($admin)->delete("/vi/admin/partners/{$partner->id}")->assertForbidden();
        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'X'])->assertForbidden();
        $this->assertDatabaseCount('partners', 1);
    }

    public function test_index_filters_by_group_status_and_name(): void
    {
        $strategic = PartnerGroup::query()->create(['key' => 'strategic', 'name' => 'Đối tác chiến lược', 'sort_order' => 1]);
        Partner::factory()->for($this->customers, 'group')->create(['name' => 'Panasonic']);
        Partner::factory()->for($this->customers, 'group')->inactive()->create(['name' => 'Canon']);
        Partner::factory()->for($strategic, 'group')->create(['name' => 'Toyota']);
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->get('/vi/admin/partners')->assertOk()
            ->assertSee('Panasonic')->assertSee('Canon')->assertSee('Toyota')
            ->assertSee('Khách hàng tiêu biểu')->assertSee('Đối tác chiến lược');

        $this->actingAs($admin)->get("/vi/admin/partners?group={$strategic->id}")->assertOk()
            ->assertSee('Toyota')->assertDontSee('Panasonic')
            // One group, nothing filtered out: the rows can be dragged.
            ->assertSee('data-sortable="1"', false);

        $this->actingAs($admin)->get("/vi/admin/partners?group={$this->customers->id}&status=0")->assertOk()
            ->assertSee('Canon')->assertDontSee('Panasonic')
            ->assertSee('data-sortable="0"', false);

        $this->actingAs($admin)->get('/vi/admin/partners?q=pana')->assertOk()
            ->assertSee('Panasonic')->assertDontSee('Toyota');
    }

    public function test_create_page_renders_and_sends_to_group_creation_when_there_is_no_group(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->get('/vi/admin/partners/create')->assertOk()
            ->assertSee('name="image_file"', false)
            ->assertSee('data-media-folder="brands"', false)
            ->assertSee('Khách hàng tiêu biểu');

        $this->customers->delete();
        $this->actingAs($admin)->get('/vi/admin/partners/create')->assertRedirect(route('admin.partner-groups.create', ['locale' => 'vi']));
    }

    public function test_store_validates_the_input(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->post('/vi/admin/partners', [])
            ->assertSessionHasErrors(['partner_group_id', 'name', 'image_url']);

        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload(['url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload(['image_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('image_url');

        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload(['partner_group_id' => 999]))
            ->assertSessionHasErrors('partner_group_id');

        $this->assertDatabaseCount('partners', 0);
    }

    public function test_store_keeps_the_logo_as_a_relative_path_and_appends_to_the_group(): void
    {
        $admin = $this->adminWith(self::ALL);
        Partner::factory()->for($this->customers, 'group')->create(['sort_order' => 4]);

        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload([
            'name' => 'Panasonic',
            // What the media picker hands over for a file of this site.
            'image_url' => url('/storage/brands/panasonic.png'),
            'url' => 'https://www.panasonic.com',
        ]))->assertRedirect(route('admin.partners.index', ['locale' => 'vi', 'group' => $this->customers->id]));

        $partner = Partner::query()->where('name', 'Panasonic')->firstOrFail();
        $this->assertSame('/storage/brands/panasonic.png', $partner->getRawOriginal('logo_path'));
        $this->assertSame(url('/storage/brands/panasonic.png'), $partner->logo_path);
        $this->assertSame('https://www.panasonic.com', $partner->url);
        $this->assertSame(5, $partner->sort_order);
        $this->assertTrue($partner->is_active);
        $this->assertTrue(AdminActivityLog::query()->where('action', 'created')->where('subject_type', Partner::class)->exists());
    }

    public function test_an_uploaded_logo_goes_to_the_media_library_and_is_stored_relative(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->post('/vi/admin/partners', $this->payload([
            'image_url' => null,
            'image_file' => UploadedFile::fake()->image('nidec.png', 200, 80),
        ]))->assertSessionHasNoErrors();

        $stored = Partner::query()->firstOrFail()->getRawOriginal('logo_path');
        $this->assertStringStartsWith('/storage/brands/nidec-', $stored);
        Storage::disk('public')->assertExists(substr($stored, strlen('/storage/')));
    }

    public function test_update_keeps_the_logo_when_none_is_chosen_and_can_hide_the_partner(): void
    {
        $admin = $this->adminWith(self::ALL);
        $partner = Partner::factory()->for($this->customers, 'group')->create(['name' => 'Old', 'logo_path' => '/storage/brands/old.png']);

        $this->actingAs($admin)->get("/vi/admin/partners/{$partner->id}/edit")->assertOk()->assertSee('Old');

        $this->actingAs($admin)->put("/vi/admin/partners/{$partner->id}", [
            'partner_group_id' => $this->customers->id,
            'name' => 'New name',
            'image_url' => '',
            'url' => '/lien-he',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $partner->refresh();
        $this->assertSame('New name', $partner->name);
        $this->assertSame('/storage/brands/old.png', $partner->getRawOriginal('logo_path'));
        $this->assertSame('/lien-he', $partner->url);
        $this->assertFalse($partner->is_active);
    }

    public function test_delete_needs_the_delete_permission(): void
    {
        $partner = Partner::factory()->for($this->customers, 'group')->create();

        $this->actingAs($this->adminWith(['partners.view', 'partners.update']))
            ->delete("/vi/admin/partners/{$partner->id}")->assertForbidden();
        $this->assertDatabaseHas('partners', ['id' => $partner->id]);

        $this->actingAs($this->adminWith(self::ALL))
            ->delete("/vi/admin/partners/{$partner->id}")->assertRedirect();
        $this->assertDatabaseMissing('partners', ['id' => $partner->id]);
    }

    public function test_bulk_show_hide_and_delete(): void
    {
        [$a, $b] = Partner::factory()->count(2)->for($this->customers, 'group')->create();
        $editor = $this->adminWith(['partners.view', 'partners.update']);

        $this->actingAs($editor)->patch('/vi/admin/partners/bulk', ['ids' => [$a->id, $b->id], 'action' => 'deactivate'])->assertRedirect();
        $this->assertSame(0, Partner::query()->where('is_active', true)->count());

        // Deleting through the shared endpoint still costs partners.delete.
        $this->actingAs($editor)->patch('/vi/admin/partners/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertForbidden();
        $this->assertDatabaseCount('partners', 2);

        $this->actingAs($this->adminWith(self::ALL))->patch('/vi/admin/partners/bulk', ['ids' => [$a->id, $b->id], 'action' => 'delete'])->assertRedirect();
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_drag_sort_saves_the_order(): void
    {
        [$a, $b, $c] = Partner::factory()->count(3)->for($this->customers, 'group')->create();

        $this->actingAs($this->adminWith(['partners.view']))
            ->postJson('/vi/admin/partners/sort', ['ids' => [$c->id, $a->id, $b->id]])->assertForbidden();

        $this->actingAs($this->adminWith(self::ALL))
            ->postJson('/vi/admin/partners/sort', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()->assertJsonPath('message', __('partners.messages.sorted'));

        $this->assertSame([$c->id, $a->id, $b->id], Partner::query()->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_group_key_is_slugged_from_the_name_and_numbered_when_taken(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->get('/vi/admin/partner-groups/create')->assertOk();
        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'Đối tác chiến lược'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'Đối tác chiến lược'])->assertSessionHasNoErrors();

        $this->assertSame(['doi-tac-chien-luoc', 'doi-tac-chien-luoc-2'], PartnerGroup::query()->where('key', 'like', 'doi-tac%')->orderBy('id')->pluck('key')->all());
    }

    public function test_group_key_can_be_chosen_on_create_but_must_be_a_unique_slug(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'Đối tác', 'key' => 'Strategic'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('partner_groups', ['key' => 'strategic', 'name' => 'Đối tác']);

        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'Khác', 'key' => 'customers'])->assertSessionHasErrors('key');
        $this->actingAs($admin)->post('/vi/admin/partner-groups', ['name' => 'Khác', 'key' => 'có dấu'])->assertSessionHasErrors('key');
    }

    public function test_group_key_never_changes_on_update(): void
    {
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->get("/vi/admin/partner-groups/{$this->customers->id}/edit")->assertOk()->assertSee('customers');
        $this->actingAs($admin)->put("/vi/admin/partner-groups/{$this->customers->id}", ['name' => 'Khách hàng', 'key' => 'hacked', 'sort_order' => 3])
            ->assertSessionHasNoErrors();

        $this->customers->refresh();
        $this->assertSame('customers', $this->customers->key);
        $this->assertSame('Khách hàng', $this->customers->name);
        $this->assertSame(3, $this->customers->sort_order);
    }

    public function test_a_group_with_partners_cannot_be_deleted(): void
    {
        Partner::factory()->count(2)->for($this->customers, 'group')->create();
        $admin = $this->adminWith(self::ALL);

        $this->actingAs($admin)->from('/vi/admin/partner-groups')
            ->delete("/vi/admin/partner-groups/{$this->customers->id}")
            ->assertRedirect('/vi/admin/partner-groups')
            ->assertSessionHas('error', __('partners.groups.messages.delete_in_use', ['count' => 2]));
        $this->assertDatabaseHas('partner_groups', ['id' => $this->customers->id]);

        // The database refuses too, whatever path the delete takes.
        try {
            DB::table('partner_groups')->where('id', $this->customers->id)->delete();
            $this->fail('Deleting a group that still has partners must be refused by the foreign key.');
        } catch (QueryException) {
            $this->assertDatabaseHas('partner_groups', ['id' => $this->customers->id]);
        }

        Partner::query()->delete();
        $this->actingAs($admin)->delete("/vi/admin/partner-groups/{$this->customers->id}")->assertRedirect();
        $this->assertDatabaseMissing('partner_groups', ['id' => $this->customers->id]);
    }

    public function test_the_upgrade_gives_partner_permissions_to_whoever_managed_banners(): void
    {
        $bannerEditor = $this->adminWith(['banners.view', 'banners.update']);
        $other = $this->adminWith(['posts.view']);
        // As on a site installed before this release: no partners.* rows at all.
        Permission::query()->where('code', 'like', 'partners.%')->delete();

        (require database_path('migrations/2026_10_28_100100_grant_partner_permissions.php'))->up();

        $bannerEditor->role->refresh()->unsetRelation('permissionRecords');
        $this->assertEqualsCanonicalizing(['banners.view', 'banners.update', 'partners.view', 'partners.update'], $bannerEditor->role->permissions);
        $this->assertSame(['posts.view'], $other->role->refresh()->permissions);
        $this->assertSame(4, Permission::query()->where('code', 'like', 'partners.%')->count());
    }

    public function test_the_sidebar_shows_partners_only_with_the_permission(): void
    {
        $this->actingAs($this->adminWith(['posts.view']))->get('/vi/admin')
            ->assertOk()->assertDontSee(route('admin.partners.index', ['locale' => 'vi']), false);

        $this->actingAs($this->adminWith(['partners.view']))->get('/vi/admin')
            ->assertOk()
            ->assertSee(route('admin.partners.index', ['locale' => 'vi']), false)
            ->assertSee(route('admin.partner-groups.index', ['locale' => 'vi']), false);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'partner_group_id' => $this->customers->id,
            'name' => 'Nidec',
            'image_url' => '/storage/brands/nidec.png',
            'url' => '',
            'is_active' => '1',
        ], $overrides);
    }

    /** @param  array<int, string>  $permissions */
    private function adminWith(array $permissions): User
    {
        $role = Role::query()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }
}
