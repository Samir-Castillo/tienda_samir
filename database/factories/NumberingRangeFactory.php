<?php

namespace Database\Factories;

use App\Models\NumberingRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberingRange>
 */
class NumberingRangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factus_id' => null,
            'prefix' => strtoupper(fake()->lexify('????')),
            'name' => fake()->words(2, true),
            'range_from' => 1,
            'range_to' => 1000,
            'current_number' => 1,
            'resolution_number' => null,
            'resolution_date' => null,
            'active' => true,
            'raw_data' => null,
        ];
    }
}
