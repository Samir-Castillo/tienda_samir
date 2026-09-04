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

function factusTestInvoice(array $overrides = [], ?UnitOfMeasure $unit = null): Invoice
{
    $customer = Customer::factory()->create([
        'identification_document_code' => 'CC',
        'legal_organization_code' => 'PN',
        'tribute_code' => '01',
        'municipality_code' => '68679',
        'factus_municipality_id' => 980,
        'responsibilities' => ['R-99-PN'],
    ]);

    $unit ??= UnitOfMeasure::factory()->create(['code' => '94', 'factus_id' => 70]);
    $tax = Tax::factory()->create(['code' => '01']);
    $product = Product::factory()
        ->withTax($tax, 19, false)
        ->create(['price' => 50000, 'unit_measure_id' => $unit->id, 'standard_code' => '999']);

    $range = NumberingRange::factory()->create(['factus_id' => 8, 'active' => true]);

    $invoice = Invoice::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'numbering_range_id' => $range->id,
        'document' => '01',
        'operation_type' => '10',
        'status' => InvoiceStatus::Draft,
    ], $overrides));

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

    return $invoice->load(['customer', 'numberingRange', 'items.taxes', 'items.product.unitOfMeasure', 'payments', 'allowanceCharges']);
}

describe('FactusService', function () {
    beforeEach(function () {
        Cache::flush();
    });

    test('it maps an invoice to the Factus V1 payload', function () {
        $invoice = factusTestInvoice();

        $service = new FactusService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($service, $invoice);

        expect($payload['numbering_range_id'])->toBe(8);
        expect($payload['document'])->toBe('01');
        expect($payload['reference_code'])->toBe($invoice->reference_code);
        expect($payload['operation_type'])->toBe('10');
        expect($payload['payment_form'])->toBe('1');
        expect($payload['payment_method_code'])->toBe('10');
        expect($payload['send_email'])->toBeTrue();

        expect($payload['customer']['identification_document_id'])->toBe(3);
        expect($payload['customer']['legal_organization_id'])->toBe(2);
        expect($payload['customer']['tribute_id'])->toBe(18);
        expect($payload['customer']['municipality_id'])->toBe(980);
        expect($payload['customer']['responsibilities'])->toBe(['R-99-PN']);

        expect($payload['items'][0]['code_reference'])->toBe($invoice->items->first()->code_reference);
        expect($payload['items'][0]['quantity'])->toBe(2);
        expect($payload['items'][0]['price'])->toBe(50000.0);
        expect($payload['items'][0]['tax_rate'])->toBe('19.00');
        expect($payload['items'][0]['unit_measure_id'])->toBe(70);
        expect($payload['items'][0]['standard_code_id'])->toBe(1);
        expect($payload['items'][0]['is_excluded'])->toBe(0);
        expect($payload['items'][0]['tribute_id'])->toBe(1);
    });

    test('it sends the Factus municipality id, not the local DIAN code', function () {
        $invoice = factusTestInvoice();

        expect($invoice->customer->municipality_code)->toBe('68679');
        expect($invoice->customer->factus_municipality_id)->toBe(980);

        $service = new FactusService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($service, $invoice);

        expect($payload['customer']['municipality_id'])->toBe(980);
    });

    test('it fails with a descriptive error when a unit of measure has no factus_id', function () {
        $unit = UnitOfMeasure::factory()->create(['code' => 'OTHER', 'factus_id' => null]);

        $invoice = factusTestInvoice([], $unit);

        $service = new FactusService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($service, $invoice->fresh()))
            ->toThrow(
                RuntimeException::class,
                'No se pudo resolver la unidad de medida de Factus'
            );
    });

    test('it fails when an item tax code cannot be mapped to a Factus tribute', function () {
        $invoice = factusTestInvoice();

        $item = $invoice->items->first();
        $item->taxes()->update(['code' => 'TAX-19']);

        $service = new FactusService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($service, $invoice->fresh()->load('items.taxes')))
            ->toThrow(
                RuntimeException::class,
                'no se puede mapear de forma segura'
            );
    });

    test('it fails when an item has multiple non-excluded taxes', function () {
        $invoice = factusTestInvoice();

        $item = $invoice->items->first();
        $item->taxes()->delete();
        $item->taxes()->insert([
            ['invoice_item_id' => $item->id, 'code' => '01', 'rate' => 19, 'is_excluded' => false, 'amount' => 9500, 'created_at' => now(), 'updated_at' => now()],
            ['invoice_item_id' => $item->id, 'code' => '04', 'rate' => 8, 'is_excluded' => false, 'amount' => 4000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new FactusService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($service, $invoice->fresh()->load('items.taxes')))
            ->toThrow(
                RuntimeException::class,
                'más de un impuesto no excluido'
            );
    });

    test('it fails when an item has no taxes', function () {
        $invoice = factusTestInvoice();

        $item = $invoice->items->first();
        $item->taxes()->delete();

        $service = new FactusService;
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildPayload');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($service, $invoice->fresh()->load('items.taxes')))
            ->toThrow(
                RuntimeException::class,
                'no tiene impuestos configurados'
            );
    });

    test('it sends the invoice to Factus and saves the response', function () {
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
                        'id' => 820,
                        'number' => 'SETP990000493',
                        'status' => 1,
                        'cufe' => 'cufe-hash-value',
                        'qr' => 'https://dian.gov.co/qr',
                        'qr_image' => 'data:image/png;base64,AAAA',
                        'validated' => '09-01-2025 01:56:16 PM',
                        'errors' => [],
                    ],
                ],
            ]),
        ]);

        $invoice = factusTestInvoice();

        $service = new FactusService;
        $result = $service->sendInvoice($invoice);

        expect($result['success'])->toBeTrue();

        $fresh = $invoice->fresh();

        expect($fresh->factus_id)->toBe('820');
        expect($fresh->factus_number)->toBe('SETP990000493');
        expect($fresh->status)->toBe(InvoiceStatus::Validated);
        expect($fresh->cufe)->toBe('cufe-hash-value');
        expect($fresh->qr_code)->toBe('https://dian.gov.co/qr');
        expect($fresh->qr_image)->toBe('data:image/png;base64,AAAA');
        expect($fresh->validated_at)->not->toBeNull();
        expect($fresh->factus_errors)->toBe([]);

        expect($fresh->factusRequests()->count())->toBe(1);
        $request = $fresh->factusRequests()->first();
        expect($request->success)->toBeTrue();
        expect($request->endpoint)->toBe('https://api-sandbox.factus.com.co/v1/bills/validate');
    });

    test('it marks the invoice as rejected on Factus failure', function () {
        Http::fake([
            'https://api-sandbox.factus.com.co/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ]),
            'https://api-sandbox.factus.com.co/v1/bills/validate' => Http::response([
                'status' => 'Error',
                'message' => 'Datos invalidos',
                'errors' => ['FAJ44b' => 'Error de validacion'],
            ], 422),
        ]);

        $invoice = factusTestInvoice();

        $service = new FactusService;
        $result = $service->sendInvoice($invoice);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toBe('Datos invalidos');

        $fresh = $invoice->fresh();

        expect($fresh->status)->toBe(InvoiceStatus::Rejected);
        expect($fresh->factus_errors)->toBe(['FAJ44b' => 'Error de validacion']);
        expect($fresh->factusRequests()->count())->toBe(1);
    });

    test('an invoice that is not draft cannot be sent via the endpoint', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = factusTestInvoice(['status' => InvoiceStatus::Validated]);

        $this->postJson("/api/ventas/{$invoice->id}/factus")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo se pueden enviar facturas en estado borrador.');
    });
});
