<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed sample products for development.
     *
     * NOTE: The codes below are generic development values, not official
     * Factus standard codes.
     */
    public function run(): void
    {
        $unitUnidad = UnitOfMeasure::query()->where('code', 'UOM-UND')->firstOrFail();
        $tax19 = Tax::query()->where('code', 'TAX-19')->firstOrFail();

        $products = [
            [
                'code' => 'PROD-DEMO-001',
                'name' => 'Producto de Ejemplo Uno',
                'description' => 'Producto de desarrollo para pruebas.',
                'price' => 10000,
                'standard_code' => 'DEV-SC-001',
            ],
            [
                'code' => 'PROD-DEMO-002',
                'name' => 'Producto de Ejemplo Dos',
                'description' => 'Producto de desarrollo para pruebas.',
                'price' => 25000,
                'standard_code' => 'DEV-SC-002',
            ],
        ];

        foreach ($products as $product) {
            $created = Product::query()->firstOrCreate(
                ['code' => $product['code']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'unit_measure_id' => $unitUnidad->id,
                    'standard_code' => $product['standard_code'],
                    'active' => true,
                ],
            );

            $created->taxes()->syncWithoutDetaching([
                $tax19->id => [
                    'rate' => 19,
                    'is_excluded' => false,
                ],
            ]);
        }
    }
}
