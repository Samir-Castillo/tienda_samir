<?php

use App\Models\Customer;
use App\Models\FactusRequest;
use App\Models\Invoice;
use App\Models\InvoiceAllowanceCharge;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;

test('a customer can have multiple invoices', function () {
    $customer = Customer::factory()->create();

    Invoice::factory()->count(3)->create(['customer_id' => $customer->id]);

    expect($customer->invoices()->count())->toBe(3);
});

test('an invoice belongs to a customer', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

    expect($invoice->customer->id)->toBe($customer->id);
});

test('an invoice can have multiple invoice items', function () {
    $invoice = Invoice::factory()->create();

    InvoiceItem::factory()->count(3)->create(['invoice_id' => $invoice->id]);

    expect($invoice->items()->count())->toBe(3);
});

test('an invoice item belongs to a product', function () {
    $product = Product::factory()->create();
    $item = InvoiceItem::factory()->create(['product_id' => $product->id]);

    expect($item->product->id)->toBe($product->id);
});

test('a product belongs to a unit of measure', function () {
    $unit = UnitOfMeasure::factory()->create();
    $product = Product::factory()->create(['unit_measure_id' => $unit->id]);

    expect($product->unitOfMeasure->id)->toBe($unit->id);
});

test('a product can have multiple taxes', function () {
    $product = Product::factory()->create();
    $taxA = Tax::factory()->create();
    $taxB = Tax::factory()->create();

    $product->taxes()->attach([
        $taxA->id => ['rate' => 19, 'is_excluded' => false],
        $taxB->id => ['rate' => 5, 'is_excluded' => false],
    ]);

    expect($product->taxes()->count())->toBe(2);
    expect($product->taxes->contains($taxA))->toBeTrue();
    expect($product->taxes->contains($taxB))->toBeTrue();
});

test('an invoice item can have multiple invoice item taxes', function () {
    $item = InvoiceItem::factory()->create();

    InvoiceItemTax::query()->create([
        'invoice_item_id' => $item->id,
        'code' => 'TAX-A',
        'rate' => 19,
        'is_excluded' => false,
        'amount' => 1900,
    ]);
    InvoiceItemTax::query()->create([
        'invoice_item_id' => $item->id,
        'code' => 'TAX-B',
        'rate' => 5,
        'is_excluded' => false,
        'amount' => 500,
    ]);

    expect($item->taxes()->count())->toBe(2);
});

test('an invoice can have multiple payments', function () {
    $invoice = Invoice::factory()->create();

    InvoicePayment::factory()->count(2)->create(['invoice_id' => $invoice->id]);

    expect($invoice->payments()->count())->toBe(2);
});

test('an invoice can have multiple allowance charges', function () {
    $invoice = Invoice::factory()->create();

    InvoiceAllowanceCharge::query()->create([
        'invoice_id' => $invoice->id,
        'concept_type' => 'DEV',
        'is_surcharge' => false,
        'reason' => 'Descuento de desarrollo',
        'base_amount' => 1000,
        'amount' => 100,
    ]);
    InvoiceAllowanceCharge::query()->create([
        'invoice_id' => $invoice->id,
        'concept_type' => 'DEV',
        'is_surcharge' => false,
        'reason' => 'Segundo descuento de desarrollo',
        'base_amount' => 500,
        'amount' => 50,
    ]);

    expect($invoice->allowanceCharges()->count())->toBe(2);
});

test('an invoice can have multiple factus requests', function () {
    $invoice = Invoice::factory()->create();

    FactusRequest::query()->create([
        'invoice_id' => $invoice->id,
        'endpoint' => 'https://api-sandbox.factus.com.co/v1/bills/validate',
        'method' => 'POST',
        'request_body' => [],
        'response_body' => [],
        'http_status' => 200,
        'success' => true,
        'error_message' => null,
    ]);
    FactusRequest::query()->create([
        'invoice_id' => $invoice->id,
        'endpoint' => 'https://api-sandbox.factus.com.co/v1/bills/validate',
        'method' => 'POST',
        'request_body' => [],
        'response_body' => [],
        'http_status' => 500,
        'success' => false,
        'error_message' => 'Error de desarrollo',
    ]);

    expect($invoice->factusRequests()->count())->toBe(2);
});
