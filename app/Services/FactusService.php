<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\FactusRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactusService
{
    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('factus.base_url');
        $this->timeout = (int) config('factus.timeout', 30);
    }

    /**
     * Retrieve an existing bill from Factus by its number.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getBill(string $factusNumber): array
    {
        if (empty($factusNumber)) {
            return ['success' => false, 'error' => 'El número de factura de Factus no puede estar vacío.'];
        }

        $endpoint = config('factus.endpoints.bills_show');
        $url = $this->baseUrl.$endpoint.'/'.$factusNumber;

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->getAccessToken())
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($url);
        } catch (\Exception $e) {
            Log::error('Factus GET bill connection error', [
                'factus_number' => $factusNumber,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Error de conexión con Factus: '.$e->getMessage()];
        }

        $status = $response->status();
        $body = $response->json();

        if ($response->successful()) {
            return ['success' => true, 'data' => $body];
        }

        $errorMessage = $body['message'] ?? 'Error desconocido de Factus';

        Log::error('Factus GET bill error', [
            'factus_number' => $factusNumber,
            'status' => $status,
            'error' => $errorMessage,
            'response' => $body,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Send an invoice to Factus for creation and DIAN validation.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function sendInvoice(Invoice $invoice): array
    {
        $invoice->load(['customer', 'numberingRange', 'items.taxes', 'items.product.unitOfMeasure', 'payments', 'allowanceCharges']);

        $payload = $this->buildPayload($invoice);

        $endpoint = config('factus.endpoints.bills_validate');
        $url = $this->baseUrl.$endpoint;

        $response = Http::timeout($this->timeout)
            ->withToken($this->getAccessToken())
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($url, $payload);

        $success = $response->successful();
        $status = $response->status();
        $body = $response->json();

        $this->logRequest(
            invoice: $invoice,
            endpoint: $url,
            method: 'POST',
            requestBody: $payload,
            responseBody: $body,
            httpStatus: $status,
            success: $success,
        );

        if ($success) {
            $this->saveResponse($invoice, $body);

            return ['success' => true, 'data' => $body];
        }

        $errorMessage = $body['message'] ?? 'Error desconocido de Factus';

        Log::error('Factus API error', [
            'invoice_id' => $invoice->id,
            'status' => $status,
            'error' => $errorMessage,
            'response' => $body,
        ]);

        $invoice->update([
            'status' => InvoiceStatus::Rejected,
            'factus_errors' => $body['errors'] ?? [$errorMessage],
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Build the Factus V1 payload from an Invoice model.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Invoice $invoice): array
    {
        $payload = [
            'numbering_range_id' => $invoice->numberingRange?->factus_id,
            'document' => $invoice->document,
            'reference_code' => $invoice->reference_code,
            'observation' => $invoice->observation ?? '',
            'payment_form' => $invoice->payments->first()?->payment_form ?? '1',
            'payment_method_code' => $invoice->payments->first()?->payment_method_code ?? '10',
            'operation_type' => $invoice->operation_type,
            'send_email' => $invoice->send_email,
            'customer' => $this->mapCustomer($invoice->customer),
            'items' => $this->mapItems($invoice),
        ];

        if ($invoice->payments->first()?->payment_form === '2' && $invoice->payments->first()?->due_date) {
            $payload['payment_due_date'] = $invoice->payments->first()->due_date->format('Y-m-d');
        }

        if ($invoice->allowanceCharges->isNotEmpty()) {
            $payload['allowance_charges'] = $this->mapAllowanceCharges($invoice);
        }

        return $payload;
    }

    /**
     * Map a Customer to the Factus V1 customer object.
     *
     * @return array<string, mixed>
     */
    private function mapCustomer(Customer $customer): array
    {
        $map = new CustomerFieldMapper;

        return [
            'identification_document_id' => $map->identificationDocumentId($customer->identification_document_code),
            'identification' => $customer->identification,
            'dv' => $customer->dv,
            'company' => $customer->company ?? '',
            'trade_name' => $customer->trade_name ?? '',
            'names' => $customer->names ?? '',
            'address' => $customer->address ?? '',
            'email' => $customer->email ?? '',
            'phone' => $customer->phone ?? '',
            'legal_organization_id' => $map->legalOrganizationId($customer->legal_organization_code),
            'tribute_id' => $map->tributeId($customer->tribute_code),
            'municipality_id' => $customer->factus_municipality_id,
            'responsibilities' => $customer->responsibilities ?? ['R-99-PN'],
        ];
    }

    /**
     * Map invoice items to the Factus V1 items array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(Invoice $invoice): array
    {
        $map = new CustomerFieldMapper;

        return $invoice->items->map(function ($item) use ($map) {
            $taxRate = $item->taxes->where('is_excluded', false)->first()?->rate ?? '0.00';

            return [
                'code_reference' => $item->code_reference,
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
                'discount_rate' => (float) $item->discount_rate,
                'price' => (float) $item->unit_price,
                'tax_rate' => number_format((float) $taxRate, 2, '.', ''),
                'unit_measure_id' => $this->resolveUnitMeasureId($item),
                'standard_code_id' => $map->standardCodeId($item->standard_code),
                'is_excluded' => $item->taxes->contains('is_excluded', true) ? 1 : 0,
                'tribute_id' => $this->resolveItemTribute($item, $map),
            ];
        })->toArray();
    }

    /**
     * Resolve the Factus tribute_id for an invoice item from its applied taxes.
     *
     * Factus V1 supports a single tax_rate / tribute_id per line, so an item
     * with more than one non-excluded tax cannot be represented and would be
     * sent inconsistently; in that case the invoice is aborted.
     */
    private function resolveItemTribute(InvoiceItem $item, CustomerFieldMapper $map): int
    {
        $taxes = $item->taxes;

        $tributaryTaxes = $taxes->where('is_excluded', false);

        if ($tributaryTaxes->count() > 1) {
            throw new \RuntimeException(
                "El item '{$item->code_reference}' tiene más de un impuesto no excluido. "
                .'Factus V1 solo admite un tributo y una tarifa por línea; '
                .'no se puede enviar la factura de forma consistente.'
            );
        }

        $code = $tributaryTaxes->first()?->code
            ?? $taxes->first()?->code;

        if ($code === null) {
            throw new \RuntimeException(
                "El item '{$item->code_reference}' no tiene impuestos configurados. "
                .'No se puede determinar su tributo en Factus.'
            );
        }

        return $map->productTributeId($code);
    }

    /**
     * Resolve the Factus units-of-measure ID for an invoice item.
     *
     * The local product only stores the local units_of_measure id; Factus
     * expects its own catalog id, stored on UnitOfMeasure.factus_id. If it is
     * not configured, fail with a clear error instead of inventing an id.
     */
    private function resolveUnitMeasureId(InvoiceItem $item): int
    {
        $product = $item->product;
        $unit = $product?->unitOfMeasure;

        $factusId = $unit?->factus_id ?? null;

        if ($factusId === null) {
            $productId = $product?->id;
            $productCode = $product?->code;
            $unitId = $product?->unit_measure_id;

            throw new \RuntimeException(
                "No se pudo resolver la unidad de medida de Factus para el producto '{$productCode}' (id {$productId}). "
                ."La unidad de medida local (id {$unitId}) no tiene configurado factus_id. "
                .'Configure el factus_id en la tabla units_of_measure antes de facturar este producto.'
            );
        }

        return $factusId;
    }

    /**
     * Map allowance charges to Factus V1 format.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapAllowanceCharges(Invoice $invoice): array
    {
        return $invoice->allowanceCharges->map(fn ($charge) => [
            'concept_type' => $charge->concept_type,
            'is_surcharge' => $charge->is_surcharge,
            'reason' => $charge->reason,
            'base_amount' => number_format((float) $charge->base_amount, 2, '.', ''),
            'amount' => number_format((float) $charge->amount, 2, '.', ''),
        ])->toArray();
    }

    /**
     * Save the Factus response data to the Invoice model.
     */
    private function saveResponse(Invoice $invoice, array $response): void
    {
        $bill = $response['data']['bill'] ?? [];

        $validated = $bill['status'] ?? 0;
        $status = $validated ? InvoiceStatus::Validated : InvoiceStatus::Pending;

        $validatedAt = null;
        if (! empty($bill['validated'])) {
            $validatedAt = Carbon::parse($bill['validated']);
        }

        $invoice->update([
            'factus_id' => $bill['id'] ?? null,
            'factus_number' => $bill['number'] ?? null,
            'status' => $status,
            'factus_status' => $validated ? 'validated' : 'pending',
            'cufe' => $bill['cufe'] ?? null,
            'qr_code' => $bill['qr'] ?? null,
            'qr_image' => $bill['qr_image'] ?? null,
            'validated_at' => $validatedAt,
            'factus_errors' => $bill['errors'] ?? null,
            'factus_public_url' => $bill['public_url'] ?? null,
        ]);
    }

    /**
     * Seconds to subtract from the token lifetime as a safety margin so the
     * cached token is never used after it has actually expired on the API.
     */
    private const TOKEN_SAFETY_MARGIN_SECONDS = 60;

    /**
     * Get a valid access token from Factus, cached until just before it
     * actually expires on the API so we never send a stale token.
     */
    public function getAccessToken(): string
    {
        $lifetime = 0;

        return Cache::remember(
            'factus_access_token',
            function () use (&$lifetime): int {
                return $this->computeTtl($lifetime);
            },
            function () use (&$lifetime): string {
                $endpoint = config('factus.endpoints.token');
                $url = $this->baseUrl.$endpoint;

                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->post($url, [
                        'grant_type' => 'password',
                        'client_id' => config('factus.client_id'),
                        'client_secret' => config('factus.client_secret'),
                        'username' => config('factus.username'),
                        'password' => config('factus.password'),
                    ]);

                if (! $response->successful()) {
                    Log::error('Factus auth failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    throw new \RuntimeException('No se pudo autenticar con Factus: '.$response->body());
                }

                $lifetime = (int) ($response->json('expires_in') ?? 0);

                return $response->json('access_token');
            },
        );
    }

    /**
     * Derive the cache TTL from the token's actual lifetime, leaving a safety
     * margin so the token is refreshed before it can expire on the API.
     */
    private function computeTtl(mixed $expiresIn): int
    {
        $lifetime = is_numeric($expiresIn) ? (int) $expiresIn : 0;

        if ($lifetime <= self::TOKEN_SAFETY_MARGIN_SECONDS) {
            return max(1, $lifetime);
        }

        return $lifetime - self::TOKEN_SAFETY_MARGIN_SECONDS;
    }

    /**
     * Log a request to the FactusRequest audit trail.
     */
    private function logRequest(
        Invoice $invoice,
        string $endpoint,
        string $method,
        array $requestBody,
        array $responseBody,
        int $httpStatus,
        bool $success,
    ): void {
        FactusRequest::create([
            'invoice_id' => $invoice->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_body' => $requestBody,
            'response_body' => $responseBody,
            'http_status' => $httpStatus,
            'success' => $success,
            'error_message' => $success ? null : ($responseBody['message'] ?? null),
        ]);
    }
}
