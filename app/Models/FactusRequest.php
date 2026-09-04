<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property string $endpoint
 * @property string $method
 * @property array<mixed>|null $request_body
 * @property array<mixed>|null $response_body
 * @property int|null $http_status
 * @property bool $success
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'invoice_id',
    'endpoint',
    'method',
    'request_body',
    'response_body',
    'http_status',
    'success',
    'error_message',
])]
class FactusRequest extends Model
{
    /**
     * Get the invoice this traced request relates to.
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
            'request_body' => 'array',
            'response_body' => 'array',
            'http_status' => 'integer',
            'success' => 'boolean',
        ];
    }
}
