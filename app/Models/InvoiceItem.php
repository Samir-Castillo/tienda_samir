<?php

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $product_id
 * @property string $code_reference
 * @property string $name
 * @property string|null $note
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount_rate
 * @property string $discount_amount
 * @property string $subtotal
 * @property string $total
 * @property string $unit_measure_code
 * @property string $standard_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'invoice_id',
    'product_id',
    'code_reference',
    'name',
    'note',
    'quantity',
    'unit_price',
    'discount_rate',
    'discount_amount',
    'subtotal',
    'total',
    'unit_measure_code',
    'standard_code',
])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    /**
     * Get the invoice this item belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product this item referenced at the time of the sale.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the taxes actually applied to this line item.
     *
     * @return HasMany<InvoiceItemTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceItemTax::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
