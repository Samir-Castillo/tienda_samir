<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PROD####'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 100, 5000),
            'unit_measure_id' => UnitOfMeasure::factory(),
            'standard_code' => fake()->bothify('SC#####'),
            'active' => true,
        ];
    }

    /**
     * Attach a tax to the product using the given pivot values.
     */
    public function withTax(Tax $tax, float $rate = 19.0, bool $isExcluded = false): static
    {
        return $this->afterCreating(function (Product $product) use ($tax, $rate, $isExcluded): void {
            $product->taxes()->attach($tax->id, [
                'rate' => $rate,
                'is_excluded' => $isExcluded,
            ]);
        });
    }
}
