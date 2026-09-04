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
     *
     * The Factus ID (94 => 70 for "Unidad") matches the documented sandbox
     * catalog. Units without a confirmed `factus_id` are left null so the
     * invoice payload fails loudly instead of assuming an ID.
     */
    public function run(): void
    {
        $units = [
            ['code' => 'UOM-UND', 'name' => 'Unidad', 'factus_id' => 70],
            ['code' => 'UOM-KG', 'name' => 'Kilogramo', 'factus_id' => null],
            ['code' => 'UOM-LT', 'name' => 'Litro', 'factus_id' => null],
            ['code' => 'UOM-MT', 'name' => 'Metro', 'factus_id' => null],
            ['code' => 'UOM-HR', 'name' => 'Hora', 'factus_id' => null],
            ['code' => 'UOM-SER', 'name' => 'Servicio', 'factus_id' => null],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::query()->updateOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name'], 'active' => true, 'factus_id' => $unit['factus_id']],
            );
        }
    }
}
