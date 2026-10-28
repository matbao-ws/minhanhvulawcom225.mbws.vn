<?php

namespace Database\Factories;

use App\Models\PartnerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartnerGroup>
 */
class PartnerGroupFactory extends Factory
{
    protected $model = PartnerGroup::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => ucfirst($name),
            'sort_order' => 0,
        ];
    }
}
