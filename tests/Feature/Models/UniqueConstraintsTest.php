<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use Illuminate\Database\QueryException;

test('invoice reference_code is unique', function () {
    $referenceCode = 'REF-UNIQUE-001';

    Invoice::factory()->create(['reference_code' => $referenceCode]);

    expect(fn () => Invoice::factory()->create(['reference_code' => $referenceCode]))
        ->toThrow(QueryException::class);
});

test('product code is unique', function () {
    $code = 'PROD-UNIQUE-001';

    Product::factory()->create(['code' => $code]);

    expect(fn () => Product::factory()->create(['code' => $code]))
        ->toThrow(QueryException::class);
});

test('tax code is unique', function () {
    $code = 'TAX-UNIQUE-001';

    Tax::factory()->create(['code' => $code]);

    expect(fn () => Tax::factory()->create(['code' => $code]))
        ->toThrow(QueryException::class);
});

test('unit of measure code is unique', function () {
    $code = 'UOM-UNIQUE-001';

    UnitOfMeasure::factory()->create(['code' => $code]);

    expect(fn () => UnitOfMeasure::factory()->create(['code' => $code]))
        ->toThrow(QueryException::class);
});
