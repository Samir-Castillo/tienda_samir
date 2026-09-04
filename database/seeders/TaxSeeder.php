<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Seed baseline taxes for development.
     *
     * NOTE: The codes below are generic development values. They are NOT
     * official Factus tribute codes. Confirmed official codes must be
     * obtained from the Factus API before real invoices are issued.
     */
    public function run(): void
    {
        $taxes = [
            [
                'code' => 'TAX-19',
                'name' => 'IVA 19% (desarrollo)',
                'description' => 'Impuesto al Valor Agregado 19% para desarrollo. El código oficial de Factus no está confirmado.',
            ],
            [
                'code' => 'TAX-0',
                'name' => 'IVA 0% (desarrollo)',
                'description' => 'Impuesto al Valor Agregado 0% (excluido) para desarrollo. El código oficial de Factus no está confirmado.',
            ],
        ];

        foreach ($taxes as $tax) {
            Tax::query()->firstOrCreate(
                ['code' => $tax['code']],
                [
                    'name' => $tax['name'],
                    'description' => $tax['description'],
                    'active' => true,
                ],
            );
        }
    }
}
