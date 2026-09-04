<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Seed sample customers for development.
     *
     * NOTE: The document/legal organization codes below are generic
     * development values, not official Factus codes.
     */
    public function run(): void
    {
        $customers = [
            [
                'identification_document_code' => 'DEV-CC',
                'identification' => '1000000001',
                'dv' => null,
                'legal_organization_code' => 'DEV-NAT',
                'customer' => [
                    'company' => null,
                    'trade_name' => null,
                    'names' => 'Cliente de Prueba Uno',
                ],
            ],
            [
                'identification_document_code' => 'DEV-NIT',
                'identification' => '900000000',
                'dv' => '1',
                'legal_organization_code' => 'DEV-LG',
                'customer' => [
                    'company' => 'Empresa de Prueba S.A.S.',
                    'trade_name' => 'Empresa Prueba',
                    'names' => null,
                ],
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->firstOrCreate(
                [
                    'identification_document_code' => $customer['identification_document_code'],
                    'identification' => $customer['identification'],
                    'legal_organization_code' => $customer['legal_organization_code'],
                ],
                [
                    'dv' => $customer['dv'],
                    'company' => $customer['customer']['company'],
                    'trade_name' => $customer['customer']['trade_name'],
                    'names' => $customer['customer']['names'],
                    'address' => 'Calle 123 # 45 - 67',
                    'email' => 'cliente@example.com',
                    'phone' => '3001234567',
                    'country_code' => 'CO',
                    'municipality_code' => null,
                ],
            );
        }
    }
}
