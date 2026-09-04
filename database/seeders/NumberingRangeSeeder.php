<?php

namespace Database\Seeders;

use App\Models\NumberingRange;
use Illuminate\Database\Seeder;

class NumberingRangeSeeder extends Seeder
{
    /**
     * Seed a development numbering range.
     *
     * NOTE: This is development-only data. The `factus_id`, prefix and
     * resolution are intentionally left null/placeholder until confirmed
     * with the Factus API.
     */
    public function run(): void
    {
        NumberingRange::query()->firstOrCreate(
            ['name' => 'Rango de Desarrollo'],
            [
                'factus_id' => null,
                'prefix' => 'FAC',
                'range_from' => 1,
                'range_to' => 100000,
                'current_number' => 1,
                'resolution_number' => null,
                'resolution_date' => null,
                'active' => true,
                'raw_data' => null,
            ],
        );
    }
}
