<?php

namespace App\Enums;

/**
 * Internal application status for an invoice.
 *
 * This is deliberately separate from `factus_status`, which reports the
 * state as informed by the Factus API. These values represent the lifecycle
 * of an invoice within our own application.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
