<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NumberingRange;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaService
{
    /**
     * Persist a sale as a draft invoice with its items, taxes and payment.
     *
     * @param  array{customer_id: int, items: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function create(array $data): Invoice
    {
        $customer = Customer::find($data['customer_id']);

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => ['El cliente no existe.'],
            ]);
        }

        $items = $this->resolveItems($data['items']);

        return DB::transaction(function () use ($customer, $items) {
            [$subtotal, $taxTotal] = $this->totals($items);

            $range = NumberingRange::query()
                ->where('factus_id', 8)
                ->where('active', true)
                ->first();

            if ($range === null) {
                throw ValidationException::withMessages([
                    'numbering_range' => ['No existe un rango de numeracion activo para Factura de Venta.'],
                ]);
            }

            $total = round($subtotal + $taxTotal, 2);

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'numbering_range_id' => $range->id,
                'reference_code' => 'FAC-'.Str::uuid(),
                'document' => '01',
                'operation_type' => '10',
                'issue_date' => now(),
                'observation' => null,
                'send_email' => true,
                'currency_code' => 'COP',
                'exchange_rate' => null,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => $total,
                'status' => InvoiceStatus::Draft,
            ]);

            foreach ($items as $item) {
                $this->createItem($invoice, $item);
            }

            $invoice->payments()->create([
                'payment_form' => '1',
                'payment_method_code' => '10',
                'reference_code' => null,
                'amount' => $total,
                'due_date' => null,
            ]);

            return $invoice->load(['customer', 'items.taxes', 'payments']);
        });
    }

    /**
     * Load the products for a sale and validate their availability.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $rawItems
     * @return array<int, array{product: Product, quantity: int}>
     */
    private function resolveItems(array $rawItems): array
    {
        $productIds = array_values(array_unique(array_map(
            fn (array $item): int => $item['product_id'],
            $rawItems,
        )));

        $products = Product::query()
            ->with(['unitOfMeasure', 'taxes'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $quantities = [];

        foreach ($rawItems as $item) {
            $quantities[$item['product_id']] = $item['quantity'];
        }

        $resolved = [];

        foreach (array_keys($quantities) as $productId) {
            $product = $products->get($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$productId}.product_id" => ['El producto no existe.'],
                ]);
            }

            if (! $product->active) {
                throw ValidationException::withMessages([
                    "items.{$productId}.product_id" => ['El producto no esta activo.'],
                ]);
            }

            $resolved[] = ['product' => $product, 'quantity' => $quantities[$productId]];
        }

        return $resolved;
    }

    /**
     * Compute the subtotal and tax total for the whole sale.
     *
     * @param  array<int, array{product: Product, quantity: int}>  $items
     * @return array{0: float, 1: float}
     */
    private function totals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $unitSubtotal = round((float) $item['product']->price * $item['quantity'], 2);
            $subtotal += $unitSubtotal;

            foreach ($item['product']->taxes as $tax) {
                if ($tax->pivot->is_excluded) {
                    continue;
                }

                $taxTotal += round($unitSubtotal * (float) $tax->pivot->rate / 100, 2);
            }
        }

        return [round($subtotal, 2), round($taxTotal, 2)];
    }

    /**
     * Persist an invoice item and its applied taxes.
     *
     * @param  array{product: Product, quantity: int}  $item
     */
    private function createItem(Invoice $invoice, array $item): void
    {
        $product = $item['product'];
        $unitSubtotal = round((float) $product->price * $item['quantity'], 2);
        $itemTaxTotal = 0.0;

        foreach ($product->taxes as $tax) {
            if ($tax->pivot->is_excluded) {
                continue;
            }

            $itemTaxTotal += round($unitSubtotal * (float) $tax->pivot->rate / 100, 2);
        }

        $invoiceItem = $invoice->items()->create([
            'product_id' => $product->id,
            'code_reference' => $product->code,
            'name' => $product->name,
            'note' => null,
            'quantity' => $item['quantity'],
            'unit_price' => $product->price,
            'discount_rate' => 0,
            'discount_amount' => 0,
            'subtotal' => $unitSubtotal,
            'total' => round($unitSubtotal + $itemTaxTotal, 2),
            'unit_measure_code' => $product->unitOfMeasure->code,
            'standard_code' => $product->standard_code,
        ]);

        foreach ($product->taxes as $tax) {
            $this->createItemTax($invoiceItem, $tax->code, (float) $tax->pivot->rate, (bool) $tax->pivot->is_excluded, $unitSubtotal);
        }
    }

    /**
     * Persist a single tax entry for an invoice item.
     */
    private function createItemTax(InvoiceItem $invoiceItem, string $code, float $rate, bool $isExcluded, float $unitSubtotal): void
    {
        $amount = $isExcluded ? 0.0 : round($unitSubtotal * $rate / 100, 2);

        $invoiceItem->taxes()->create([
            'code' => $code,
            'rate' => $rate,
            'is_excluded' => $isExcluded,
            'amount' => $amount,
        ]);
    }
}
