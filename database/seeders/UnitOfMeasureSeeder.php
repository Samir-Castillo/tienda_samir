<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Seed baseline units of measure for development.
     *
     * NOTE: The codes below are generic development values. They are NOT
     * official Factus unit-of-measure codes. Confirmed official codes must
     * be obtained from the Factus API before real invoices are issued.
     */
    public function run(): void
    {
        $units = [
            ['code' => 'UOM-UND', 'name' => 'Unidad'],
            ['code' => 'UOM-KG', 'name' => 'Kilogramo'],
            ['code' => 'UOM-LT', 'name' => 'Litro'],
            ['code' => 'UOM-MT', 'name' => 'Metro'],
            ['code' => 'UOM-HR', 'name' => 'Hora'],
            ['code' => 'UOM-SER', 'name' => 'Servicio'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::query()->firstOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name'], 'active' => true],
            );
        }
    }
}
