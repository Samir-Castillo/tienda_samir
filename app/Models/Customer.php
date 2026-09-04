<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $identification_document_code
 * @property string $identification
 * @property string|null $dv
 * @property string $legal_organization_code
 * @property string|null $tribute_code
 * @property string|null $company
 * @property string|null $trade_name
 * @property string|null $names
 * @property string|null $address
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $municipality_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'identification_document_code',
    'identification',
    'dv',
    'legal_organization_code',
    'tribute_code',
    'company',
    'trade_name',
    'names',
    'address',
    'email',
    'phone',
    'country_code',
    'municipality_code',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * Get the invoices issued to this customer.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
