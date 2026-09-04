<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\FactusRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\InvoicePayment;
use App\Models\NumberingRange;
use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;

function dashboardTestInvoice(InvoiceStatus $status, float $total = 119000): Invoice
{
    $customer = Customer::factory()->create([
        'identification_document_code' => 'CC',
        'legal_organization_code' => 'PN',
    ]);

    $range = NumberingRange::factory()->create(['factus_id' => 8, 'active' => true]);

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'numbering_range_id' => $range->id,
        'document' => '01',
        'operation_type' => '10',
        'status' => $status,
        'total' => $total,
        'subtotal' => round($total / 1.19, 2),
        'tax_total' => round($total - ($total / 1.19), 2),
    ]);

    InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'payment_form' => '1',
        'payment_method_code' => '10',
        'amount' => $total,
    ]);

    return $invoice;
}

function dashboardTestProduct(string $name = 'Producto Test'): Product
{
    $unit = UnitOfMeasure::firstOrCreate(['code' => '94'], ['name' => 'Unidad', 'factus_id' => 70]);
    $tax = Tax::firstOrCreate(['code' => '01'], ['name' => 'IVA 19%']);

    return Product::factory()
        ->withTax($tax, 19, false)
        ->create(['name' => $name, 'price' => 50000, 'unit_measure_id' => $unit->id, 'standard_code' => '999']);
}

function attachItemToInvoice(Invoice $invoice, Product $product, int $quantity = 1): InvoiceItem
{
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'code_reference' => $product->code,
        'name' => $product->name,
        'quantity' => $quantity,
        'unit_price' => 50000,
        'unit_measure_code' => '94',
        'standard_code' => '999',
        'subtotal' => 50000 * $quantity,
        'total' => round(50000 * $quantity * 1.19, 2),
    ]);

    InvoiceItemTax::query()->create([
        'invoice_item_id' => $item->id,
        'code' => '01',
        'rate' => 19,
        'is_excluded' => false,
        'amount' => round(50000 * $quantity * 0.19, 2),
    ]);

    return $item;
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns correct KPIs with validated invoices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    dashboardTestInvoice(InvoiceStatus::Validated, 119000);
    dashboardTestInvoice(InvoiceStatus::Validated, 238000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 50000);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['kpis']['totalFacturado'])->toEqual(357000);
    expect($props['kpis']['facturasValidadas'])->toBe(2);
    expect($props['kpis']['facturasRechazadas'])->toBe(1);
    expect($props['kpis']['ticketPromedio'])->toEqual(178500);
});

test('total facturado only considers validated invoices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    dashboardTestInvoice(InvoiceStatus::Validated, 100000);
    dashboardTestInvoice(InvoiceStatus::Draft, 200000);
    dashboardTestInvoice(InvoiceStatus::Pending, 300000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 400000);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['kpis']['totalFacturado'])->toEqual(100000);
    expect($props['kpis']['facturasValidadas'])->toBe(1);
    expect($props['kpis']['facturasRechazadas'])->toBe(1);
});

test('rejected invoices are counted correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    dashboardTestInvoice(InvoiceStatus::Rejected, 100000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 200000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 300000);
    dashboardTestInvoice(InvoiceStatus::Validated, 500000);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['kpis']['facturasRechazadas'])->toBe(3);
});

test('ticket promedio considers only validated invoices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    dashboardTestInvoice(InvoiceStatus::Validated, 100000);
    dashboardTestInvoice(InvoiceStatus::Validated, 200000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 999999);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['kpis']['ticketPromedio'])->toEqual(150000);
});

test('status distribution groups invoices correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    dashboardTestInvoice(InvoiceStatus::Draft, 10000);
    dashboardTestInvoice(InvoiceStatus::Draft, 20000);
    dashboardTestInvoice(InvoiceStatus::Pending, 30000);
    dashboardTestInvoice(InvoiceStatus::Validated, 40000);
    dashboardTestInvoice(InvoiceStatus::Validated, 50000);
    dashboardTestInvoice(InvoiceStatus::Validated, 60000);
    dashboardTestInvoice(InvoiceStatus::Rejected, 70000);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['statusDistribution']['draft'])->toBe(2);
    expect($props['statusDistribution']['pending'])->toBe(1);
    expect($props['statusDistribution']['validated'])->toBe(3);
    expect($props['statusDistribution']['rejected'])->toBe(1);
});

test('factus success rate is calculated correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    FactusRequest::query()->create([
        'endpoint' => 'https://api-sandbox.factus.com.co/v1/bills/validate',
        'method' => 'POST',
        'request_body' => [],
        'response_body' => [],
        'http_status' => 200,
        'success' => true,
    ]);
    FactusRequest::query()->create([
        'endpoint' => 'https://api-sandbox.factus.com.co/v1/bills/validate',
        'method' => 'POST',
        'request_body' => [],
        'response_body' => [],
        'http_status' => 422,
        'success' => false,
    ]);
    FactusRequest::query()->create([
        'endpoint' => 'https://api-sandbox.factus.com.co/v1/bills/validate',
        'method' => 'POST',
        'request_body' => [],
        'response_body' => [],
        'http_status' => 200,
        'success' => true,
    ]);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['factusStats']['successful'])->toBe(2);
    expect($props['factusStats']['total'])->toBe(3);
    expect($props['factusStats']['successRate'])->toEqual(66.7);
});

test('recent invoices are returned in descending order with customer name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $invoice1 = dashboardTestInvoice(InvoiceStatus::Validated, 100000);
    $invoice2 = dashboardTestInvoice(InvoiceStatus::Draft, 200000);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['recentInvoices'])->toHaveCount(2);
    $ids = array_column($props['recentInvoices'], 'id');
    expect($ids)->toContain($invoice1->id);
    expect($ids)->toContain($invoice2->id);
    expect($props['recentInvoices'][0]['displayNumber'])->not->toBeEmpty();
    expect($props['recentInvoices'][0]['customerName'])->not->toBeEmpty();
    expect($props['recentInvoices'][0]['statusLabel'])->not->toBeEmpty();
});

test('top 3 products are calculated from validated invoices only', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $productA = dashboardTestProduct('Producto A');
    $productB = dashboardTestProduct('Producto B');
    $productC = dashboardTestProduct('Producto C');
    $productD = dashboardTestProduct('Producto D');

    $validatedInvoice = dashboardTestInvoice(InvoiceStatus::Validated, 500000);
    attachItemToInvoice($validatedInvoice, $productA, 10);
    attachItemToInvoice($validatedInvoice, $productB, 5);
    attachItemToInvoice($validatedInvoice, $productC, 3);

    $draftInvoice = dashboardTestInvoice(InvoiceStatus::Draft, 300000);
    attachItemToInvoice($draftInvoice, $productD, 20);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['topProducts'])->toHaveCount(3);
    expect($props['topProducts'][0]['productName'])->toBe('Producto A');
    expect($props['topProducts'][0]['quantity'])->toBe(10);
    expect($props['topProducts'][1]['productName'])->toBe('Producto B');
    expect($props['topProducts'][1]['quantity'])->toBe(5);
    expect($props['topProducts'][2]['productName'])->toBe('Producto C');
    expect($props['topProducts'][2]['quantity'])->toBe(3);
});

test('dashboard returns zero values when database is empty', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $props = $response->inertiaProps();

    expect($props['kpis']['totalFacturado'])->toEqual(0);
    expect($props['kpis']['facturasValidadas'])->toBe(0);
    expect($props['kpis']['facturasRechazadas'])->toBe(0);
    expect($props['kpis']['ticketPromedio'])->toEqual(0);
    expect($props['statusDistribution']['draft'])->toBe(0);
    expect($props['statusDistribution']['pending'])->toBe(0);
    expect($props['statusDistribution']['validated'])->toBe(0);
    expect($props['statusDistribution']['rejected'])->toBe(0);
    expect($props['factusStats']['total'])->toBe(0);
    expect($props['factusStats']['successRate'])->toEqual(0);
    expect($props['recentInvoices'])->toBe([]);
    expect($props['topProducts'])->toBe([]);
});
