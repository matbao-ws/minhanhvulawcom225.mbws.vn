<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'partner_group_id' => PartnerGroup::factory(),
            'name' => fake()->company(),
            'logo_path' => '/storage/brands/'.fake()->unique()->slug(2).'.png',
            'url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
