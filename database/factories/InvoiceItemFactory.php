<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The product values are persisted as a snapshot, so they default to a
     * random product but never rely exclusively on it for historical data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 100, 10000);
        $quantity = fake()->numberBetween(1, 10);
        $discountRate = 0;

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'code_reference' => fake()->bothify('REF####'),
            'name' => fake()->words(3, true),
            'note' => fake()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_rate' => $discountRate,
            'discount_amount' => 0,
            'subtotal' => round($unitPrice * $quantity, 2),
            'total' => round($unitPrice * $quantity, 2),
            'unit_measure_code' => fake()->bothify('UOM####'),
            'standard_code' => fake()->bothify('SC#####'),
        ];
    }

    /**
     * Associate the item with an existing product and mirror its snapshot values.
     */
    public function withProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'code_reference' => $product->code,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'unit_measure_code' => $product->unitOfMeasure?->code,
            'standard_code' => $product->standard_code,
        ]);
    }
}
