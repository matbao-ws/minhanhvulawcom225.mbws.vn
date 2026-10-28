<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerGroup;
use App\Services\Partners\PartnerQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/public/partners?group=<key> and the Blade read behind it (PartnerQueryService): one
 * query for both, active partners only, admin order.
 */
class PublicPartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private PartnerGroup $customers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customers = PartnerGroup::query()->create(['key' => 'customers', 'name' => 'Khách hàng tiêu biểu']);
    }

    public function test_returns_the_active_partners_of_the_group_in_admin_order(): void
    {
        $strategic = PartnerGroup::query()->create(['key' => 'strategic', 'name' => 'Đối tác chiến lược']);
        $second = Partner::factory()->for($this->customers, 'group')->create(['name' => 'Canon', 'sort_order' => 2, 'logo_path' => '/storage/brands/canon.png']);
        $first = Partner::factory()->for($this->customers, 'group')->create(['name' => 'Panasonic', 'sort_order' => 1, 'logo_path' => '/storage/brands/panasonic.png', 'url' => 'https://www.panasonic.com']);
        // Same sort_order: the older row first.
        $third = Partner::factory()->for($this->customers, 'group')->create(['name' => 'Daikin', 'sort_order' => 2]);
        Partner::factory()->for($this->customers, 'group')->inactive()->create(['name' => 'Hidden', 'sort_order' => 0]);
        Partner::factory()->for($strategic, 'group')->create(['name' => 'Toyota', 'sort_order' => 0]);

        $response = $this->getJson('/api/public/partners?group=customers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['meta' => ['locale']])
            ->assertJsonPath('meta.group', 'customers')
            ->assertJsonCount(3, 'data');

        $this->assertSame([$first->id, $second->id, $third->id], array_column($response->json('data'), 'id'));
        $this->assertSame([
            'id' => $first->id,
            'name' => 'Panasonic',
            'logo_url' => url('/storage/brands/panasonic.png'),
            'url' => 'https://www.panasonic.com',
            'group' => 'customers',
        ], $response->json('data.0'));
        $this->assertSame(['id', 'name', 'logo_url', 'url', 'group'], array_keys($response->json('data.1')));
    }

    public function test_an_unknown_or_empty_group_gives_an_empty_list(): void
    {
        $this->getJson('/api/public/partners?group=nope')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/public/partners?group=customers')->assertOk()->assertJsonPath('data', []);
    }

    public function test_the_group_parameter_is_required(): void
    {
        $this->getJson('/api/public/partners')->assertStatus(422)->assertJsonPath('success', false);
        $this->getJson('/api/public/partners?group[]=x')->assertStatus(422);
    }

    public function test_blade_reads_the_same_list_through_the_shared_service(): void
    {
        Partner::factory()->for($this->customers, 'group')->create(['name' => 'B', 'sort_order' => 2]);
        Partner::factory()->for($this->customers, 'group')->create(['name' => 'A', 'sort_order' => 1]);
        Partner::factory()->for($this->customers, 'group')->inactive()->create(['name' => 'Off']);

        $partners = app(PartnerQueryService::class)->forGroup('customers');

        $this->assertSame(['A', 'B'], $partners->pluck('name')->all());
        $this->assertTrue($partners->first()->relationLoaded('group'));
        $this->assertSame(
            array_column($this->getJson('/api/public/partners?group=customers')->json('data'), 'name'),
            $partners->pluck('name')->all(),
        );
        $this->assertTrue(app(PartnerQueryService::class)->forGroup('missing')->isEmpty());
    }
}
