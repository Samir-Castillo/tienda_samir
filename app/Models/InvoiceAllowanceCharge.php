<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $concept_type
 * @property bool $is_surcharge
 * @property string $reason
 * @property string $base_amount
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['invoice_id', 'concept_type', 'is_surcharge', 'reason', 'base_amount', 'amount'])]
class InvoiceAllowanceCharge extends Model
{
    /**
     * Get the invoice this allowance or surcharge belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_surcharge' => 'boolean',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
