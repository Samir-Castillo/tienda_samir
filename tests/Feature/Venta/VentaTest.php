<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingRange;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;

function ventaRange(): NumberingRange
{
    return NumberingRange::factory()->create([
        'factus_id' => 8,
        'active' => true,
    ]);
}

function ventaProduct(int $price = 50000): Product
{
    $tax = Tax::factory()->create();

    return Product::factory()
        ->withTax($tax, 19, false)
        ->create(['price' => $price]);
}

function signedVentaPayload(int $customerId, array $items): array
{
    return [
        'customer_id' => $customerId,
        'items' => $items,
    ];
}

describe('creating a sale', function () {
    test('an authenticated user can create a sale', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        $range = ventaRange();
        $product = ventaProduct();

        $response = $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('customer.id', $customer->id);
        $response->assertJsonPath('status', InvoiceStatus::Draft->value);
    });

    test('persists the invoice with the calculated totals', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        $range = ventaRange();
        $product = ventaProduct(50000);

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        $invoice = Invoice::query()->first();

        expect($invoice->customer_id)->toBe($customer->id);
        expect($invoice->numbering_range_id)->toBe($range->id);
        expect((float) $invoice->subtotal)->toBe(100000.0);
        expect((float) $invoice->discount_total)->toBe(0.0);
        expect((float) $invoice->tax_total)->toBe(19000.0);
        expect((float) $invoice->total)->toBe(119000.0);
        expect($invoice->status)->toBe(InvoiceStatus::Draft);
        expect($invoice->document)->toBe('01');
    });

    test('persists one item per requested product', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $productA = ventaProduct();
        $productB = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $productA->id, 'quantity' => 1],
            ['product_id' => $productB->id, 'quantity' => 3],
        ]))->assertCreated();

        $invoice = Invoice::query()->first();

        expect($invoice->items()->count())->toBe(2);
        expect($invoice->items()->where('product_id', $productA->id)->first()->quantity)->toBe(1);
        expect($invoice->items()->where('product_id', $productB->id)->first()->quantity)->toBe(3);
    });

    test('snapshots the product price and codes at the time of the sale', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $product = ventaProduct(75000);

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        $product->update(['price' => 99999, 'name' => 'Nuevo nombre']);

        $item = Invoice::query()->first()->items()->first();

        expect((float) $item->unit_price)->toBe(75000.0);
        expect($item->code_reference)->toBe($product->fresh()->code);
        expect((float) $item->subtotal)->toBe(150000.0);
        expect($item->unit_measure_code)->toBe($product->unitOfMeasure->code);
        expect($item->standard_code)->toBe($product->standard_code);
    });

    test('persists the taxes applied to each item', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $product = ventaProduct(50000);
        $taxCode = $product->taxes()->first()->code;

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        $item = Invoice::query()->first()->items()->first();

        expect($item->taxes()->count())->toBe(1);

        $itemTax = $item->taxes()->first();

        expect($itemTax->code)->toBe($taxCode);
        expect((float) $itemTax->rate)->toBe(19.0);
        expect($itemTax->is_excluded)->toBeFalse();
        expect((float) $itemTax->amount)->toBe(19000.0);
    });

    test('creates a single cash payment for the total', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $product = ventaProduct(50000);

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        $invoice = Invoice::query()->first();

        expect($invoice->payments()->count())->toBe(1);

        $payment = $invoice->payments()->first();

        expect($payment->payment_method_code)->toBe('10');
        expect($payment->payment_form)->toBe('1');
        expect((float) $payment->amount)->toBe(119000.0);
    });

    test('does not change the price or stock of the products sold', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $product = ventaProduct(50000);
        $initialPrice = $product->price;

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertCreated();

        $fresh = $product->fresh();

        expect($fresh->price)->toBe($initialPrice);
        expect($fresh->active)->toBeTrue();
    });
});

describe('rejecting a sale', function () {
    it('returns 401 when the request is not authenticated', function () {
        $customer = Customer::factory()->create();
        $product = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertUnauthorized();
    });

    it('rejects a nonexistent customer', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload(99999, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    });

    it('rejects a nonexistent product', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => 99999, 'quantity' => 1],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');
    });

    it('rejects an inactive product', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        ventaRange();
        $product = Product::factory()->create(['active' => false]);

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertStatus(422);
    });

    it('rejects a quantity lower than one', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        $product = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 0],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');
    });

    it('rejects a sale that repeats the same product', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        $product = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('items');
    });

    it('persists nothing when the sale is rejected', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();
        $product = ventaProduct();

        $this->postJson('/api/ventas', signedVentaPayload($customer->id, [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ]))->assertStatus(422);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseCount('invoice_item_taxes', 0);
        $this->assertDatabaseCount('invoice_payments', 0);
    });
});
