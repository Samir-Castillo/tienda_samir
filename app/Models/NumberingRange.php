<?php

namespace App\Models;

use Database\Factories\NumberingRangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $factus_id
 * @property string|null $prefix
 * @property string|null $name
 * @property int|null $range_from
 * @property int|null $range_to
 * @property int|null $current_number
 * @property string|null $resolution_number
 * @property Carbon|null $resolution_date
 * @property bool $active
 * @property array<mixed>|null $raw_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'factus_id',
    'prefix',
    'name',
    'range_from',
    'range_to',
    'current_number',
    'resolution_number',
    'resolution_date',
    'active',
    'raw_data',
])]
class NumberingRange extends Model
{
    /** @use HasFactory<NumberingRangeFactory> */
    use HasFactory;

    /**
     * Get the invoices issued within this numbering range.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolution_date' => 'date',
            'active' => 'boolean',
            'raw_data' => 'array',
        ];
    }
}
