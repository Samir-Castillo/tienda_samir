<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Seed sample customers for development.
     *
     * Both customers use official Factus V1 codes so they can be validated
     * in the sandbox:
     *  - Persona natural: CC / PN / tribute 01 = IVA / municipality 980 (San Gil).
     *  - Persona juridica: NIT / PJ / tribute ZZ (null→21) / municipality 980.
     *
     * Both are updated idempotently via updateOrCreate keyed by identification
     * so re-running the seeder corrects any legacy placeholder values.
     */
    public function run(): void
    {
        Customer::query()->updateOrCreate(
            ['identification' => '1000000001'],
            [
                'identification_document_code' => 'CC',
                'dv' => null,
                'legal_organization_code' => 'PN',
                'tribute_code' => '01',
                'company' => null,
                'trade_name' => null,
                'names' => 'Cliente de Prueba Uno',
                'address' => 'Calle 123 # 45 - 67',
                'email' => 'cliente@example.com',
                'phone' => '3001234567',
                'country_code' => 'CO',
                'municipality_code' => '68679',
                'factus_municipality_id' => 980,
                'responsibilities' => ['R-99-PN'],
            ],
        );

        $juridico = [
            'identification_document_code' => 'NIT',
            'identification' => '900000000',
            'dv' => null,
            'legal_organization_code' => 'PJ',
            'customer' => [
                'company' => 'Empresa de Prueba S.A.S.',
                'trade_name' => 'Empresa Prueba',
                'names' => null,
            ],
        ];

        Customer::query()->updateOrCreate(
            [
                'identification' => $juridico['identification'],
            ],
            [
                'identification_document_code' => $juridico['identification_document_code'],
                'dv' => $juridico['dv'],
                'legal_organization_code' => $juridico['legal_organization_code'],
                'tribute_code' => null,
                'company' => $juridico['customer']['company'],
                'trade_name' => $juridico['customer']['trade_name'],
                'names' => $juridico['customer']['names'],
                'address' => 'Calle 123 # 45 - 67',
                'email' => 'cliente@example.com',
                'phone' => '3001234567',
                'country_code' => 'CO',
                'municipality_code' => '68679',
                'factus_municipality_id' => 980,
                'responsibilities' => ['R-99-PN'],
            ],
        );
    }
}
