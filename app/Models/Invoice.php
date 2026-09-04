<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $numbering_range_id
 * @property string $reference_code
 * @property string|null $document
 * @property string|null $operation_type
 * @property Carbon $issue_date
 * @property string|null $observation
 * @property bool $send_email
 * @property string|null $currency_code
 * @property string|null $exchange_rate
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property InvoiceStatus $status
 * @property string|null $factus_id
 * @property string|null $factus_number
 * @property string|null $factus_status
 * @property string|null $cufe
 * @property string|null $qr_code
 * @property string|null $pdf_url
 * @property string|null $xml_url
 * @property Carbon|null $validated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'customer_id',
    'numbering_range_id',
    'reference_code',
    'document',
    'operation_type',
    'issue_date',
    'observation',
    'send_email',
    'currency_code',
    'exchange_rate',
    'subtotal',
    'discount_total',
    'tax_total',
    'total',
    'status',
    'factus_id',
    'factus_number',
    'factus_status',
    'cufe',
    'qr_code',
    'pdf_url',
    'xml_url',
    'validated_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Get the customer the invoice was issued to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the numbering range the invoice was issued within.
     *
     * @return BelongsTo<NumberingRange, $this>
     */
    public function numberingRange(): BelongsTo
    {
        return $this->belongsTo(NumberingRange::class);
    }

    /**
     * Get the line items of this invoice.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the payments of this invoice.
     *
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Get the allowance or surcharge entries of this invoice.
     *
     * @return HasMany<InvoiceAllowanceCharge, $this>
     */
    public function allowanceCharges(): HasMany
    {
        return $this->hasMany(InvoiceAllowanceCharge::class);
    }

    /**
     * Get the traced requests made to the Factus API for this invoice.
     *
     * @return HasMany<FactusRequest, $this>
     */
    public function factusRequests(): HasMany
    {
        return $this->hasMany(FactusRequest::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'send_email' => 'boolean',
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'validated_at' => 'datetime',
        ];
    }
}
