<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    /**
     * Seed baseline taxes for development.
     *
     * The IVA tax uses the official Factus tribute code "01" (19%) so the
     * item tribute can be resolved to Factus tribute_id 1 for the sandbox test.
     */
    public function run(): void
    {
        $taxes = [
            [
                'code' => '01',
                'name' => 'IVA 19%',
                'description' => 'Impuesto al Valor Agregado 19% (código oficial Factus 01 = IVA).',
            ],
            [
                'code' => 'TAX-0',
                'name' => 'IVA 0% (desarrollo)',
                'description' => 'Impuesto al Valor Agregado 0% (excluido) para desarrollo. El código oficial de Factus no está confirmado.',
            ],
        ];

        foreach ($taxes as $tax) {
            Tax::query()->updateOrCreate(
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
