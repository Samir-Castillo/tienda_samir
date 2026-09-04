<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\InvoicePayment;
use App\Models\NumberingRange;
use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\FactusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function documentTestInvoice(InvoiceStatus $status = InvoiceStatus::Validated): Invoice
{
    $customer = Customer::factory()->create([
        'identification_document_code' => 'CC',
        'legal_organization_code' => 'PN',
        'tribute_code' => '01',
        'municipality_code' => '68679',
        'factus_municipality_id' => 980,
        'responsibilities' => ['R-99-PN'],
    ]);

    $unit = UnitOfMeasure::factory()->create(['code' => '94', 'factus_id' => 70]);
    $tax = Tax::factory()->create(['code' => '01']);
    $product = Product::factory()
        ->withTax($tax, 19, false)
        ->create(['price' => 50000, 'unit_measure_id' => $unit->id, 'standard_code' => '999']);

    $range = NumberingRange::factory()->create(['factus_id' => 8, 'active' => true]);

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'numbering_range_id' => $range->id,
        'document' => '01',
        'operation_type' => '10',
        'status' => $status,
        'factus_number' => $status === InvoiceStatus::Validated ? 'SETP990038695' : null,
    ]);

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'code_reference' => $product->code,
        'name' => $product->name,
        'quantity' => 2,
        'unit_price' => 50000,
        'unit_measure_code' => '94',
        'standard_code' => '999',
    ]);

    InvoiceItemTax::query()->create([
        'invoice_item_id' => $item->id,
        'code' => '01',
        'rate' => 19,
        'is_excluded' => false,
        'amount' => 19000,
    ]);

    InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'payment_form' => '1',
        'payment_method_code' => '10',
        'amount' => 119000,
    ]);

    return $invoice;
}

describe('FactusService::getBill', function () {
    beforeEach(function () {
        Cache::flush();
    });

    test('it retrieves a bill from Factus by number', function () {
        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/show/SETP990038695' => Http::response([
                'status' => 'success',
                'data' => [
                    'bill' => [
                        'id' => 79550,
                        'number' => 'SETP990038695',
                        'status' => 1,
                        'public_url' => 'https://sandbox.factus.com.co/bills/79550/view',
                    ],
                ],
            ]),
        ]);

        $service = new FactusService;
        $result = $service->getBill('SETP990038695');

        expect($result['success'])->toBeTrue();
        expect($result['data']['data']['bill']['public_url'])->toBe('https://sandbox.factus.com.co/bills/79550/view');
    });

    test('it returns an error when Factus bill is not found', function () {
        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/show/INVALID' => Http::response([
                'status' => 'error',
                'message' => 'Factura no encontrada',
            ], 404),
        ]);

        $service = new FactusService;
        $result = $service->getBill('INVALID');

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Factura no encontrada');
    });

    test('it rejects an empty factus number', function () {
        $service = new FactusService;
        $result = $service->getBill('');

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('vacío');
    });

    test('it handles connection errors gracefully', function () {
        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/show/SETP990038695' => Http::timeout(0),
        ]);

        $service = new FactusService;
        $result = $service->getBill('SETP990038695');

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('conexión');
    });
});

describe('saveResponse persists public_url', function () {
    beforeEach(function () {
        Cache::flush();
    });

    test('it saves public_url when Factus returns it', function () {
        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 600,
                'access_token' => 'fake-token',
                'refresh_token' => 'fake-refresh',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/validate' => Http::response([
                'status' => 'Created',
                'message' => 'Documento registrado y validado con exito',
                'data' => [
                    'bill' => [
                        'id' => 830,
                        'number' => 'SETP990000500',
                        'status' => 1,
                        'cufe' => 'cufe-with-url',
                        'qr' => 'https://dian.gov.co/qr',
                        'qr_image' => 'data:image/png;base64,BBBB',
                        'public_url' => 'https://sandbox.factus.com.co/bills/830/view',
                        'validated' => '09-04-2026 10:00:00 AM',
                        'errors' => [],
                    ],
                ],
            ]),
        ]);

        $customer = Customer::factory()->create([
            'identification_document_code' => 'CC',
            'legal_organization_code' => 'PN',
            'tribute_code' => '01',
            'municipality_code' => '68679',
            'factus_municipality_id' => 980,
            'responsibilities' => ['R-99-PN'],
        ]);

        $unit = UnitOfMeasure::factory()->create(['code' => '94', 'factus_id' => 70]);
        $tax = Tax::factory()->create(['code' => '01']);
        $product = Product::factory()
            ->withTax($tax, 19, false)
            ->create(['price' => 50000, 'unit_measure_id' => $unit->id, 'standard_code' => '999']);

        $range = NumberingRange::factory()->create(['factus_id' => 8, 'active' => true]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'numbering_range_id' => $range->id,
            'document' => '01',
            'operation_type' => '10',
            'status' => InvoiceStatus::Draft,
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'code_reference' => $product->code,
            'name' => $product->name,
            'quantity' => 2,
            'unit_price' => 50000,
            'unit_measure_code' => '94',
            'standard_code' => '999',
        ]);

        InvoiceItemTax::query()->create([
            'invoice_item_id' => $item->id,
            'code' => '01',
            'rate' => 19,
            'is_excluded' => false,
            'amount' => 19000,
        ]);

        InvoicePayment::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_form' => '1',
            'payment_method_code' => '10',
            'amount' => 119000,
        ]);

        $service = new FactusService;
        $result = $service->sendInvoice($invoice);

        expect($result['success'])->toBeTrue();
        expect($invoice->fresh()->factus_public_url)->toBe('https://sandbox.factus.com.co/bills/830/view');
    });
});

describe('Invoice document endpoint', function () {
    test('a draft invoice returns 422', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = documentTestInvoice(InvoiceStatus::Draft);

        $this->get("/ventas/{$invoice->id}/document")
            ->assertStatus(422);
    });

    test('a validated invoice redirects to Factus public URL', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = documentTestInvoice(InvoiceStatus::Validated);

        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/show/SETP990038695' => Http::response([
                'status' => 'success',
                'data' => [
                    'bill' => [
                        'id' => 79550,
                        'number' => 'SETP990038695',
                        'status' => 1,
                        'public_url' => 'https://sandbox.factus.com.co/bills/79550/view',
                    ],
                ],
            ]),
        ]);

        $this->get("/ventas/{$invoice->id}/document")
            ->assertRedirect('https://sandbox.factus.com.co/bills/79550/view');

        expect($invoice->fresh()->factus_public_url)->toBe('https://sandbox.factus.com.co/bills/79550/view');
    });
});
