<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\FactusService;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class VentaController extends Controller
{
    public function __construct(
        private VentaService $ventaService,
        private ?FactusService $factusService = null,
    ) {}

    /**
     * Show the new sale screen.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('ventas/Create', [
            'customers' => Customer::query()
                ->select('id', 'company', 'trade_name', 'names', 'identification')
                ->orderBy('company')
                ->get(),
            'products' => Product::query()
                ->with('unitOfMeasure', 'taxes')
                ->where('active', true)
                ->select('id', 'code', 'name', 'price', 'standard_code', 'unit_measure_id')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'unit_measure_code' => $product->unitOfMeasure->code,
                    'taxes' => $product->taxes
                        ->map(fn ($tax): array => [
                            'code' => $tax->code,
                            'rate' => (float) $tax->pivot->rate,
                            'is_excluded' => (bool) $tax->pivot->is_excluded,
                        ])
                        ->values(),
                ])
                ->values(),
        ]);
    }

    /**
     * Persist a sale.
     */
    public function store(StoreVentaRequest $request): JsonResponse
    {
        $invoice = $this->ventaService->create($request->validated());

        return response()->json($invoice, 201);
    }

    /**
     * Send an existing invoice to Factus for electronic validation.
     */
    public function sendToFactus(Invoice $invoice): JsonResponse
    {
        if ($invoice->status->value !== 'draft') {
            return response()->json([
                'message' => 'Solo se pueden enviar facturas en estado borrador.',
            ], 422);
        }

        $factusService = $this->factusService ?? app(FactusService::class);

        $result = $factusService->sendInvoice($invoice);

        if ($result['success']) {
            return response()->json([
                'message' => 'Factura enviada y validada exitosamente.',
                'invoice' => $invoice->fresh(),
            ]);
        }

        return response()->json([
            'message' => 'Error al enviar la factura a Factus.',
            'error' => $result['error'],
        ], 422);
    }

    /**
     * View the validated invoice document (inline PDF proxy from Factus).
     */
    public function document(Invoice $invoice): RedirectResponse|Response
    {
        if ($invoice->status->value !== 'validated') {
            abort(422, 'Solo se puede consultar el documento de facturas validadas.');
        }

        $factusService = $this->factusService ?? app(FactusService::class);

        $result = $factusService->getBill($invoice->factus_number);

        if (! $result['success']) {
            abort(502, 'Error al consultar el documento en Factus: '.$result['error']);
        }

        $publicUrl = $result['data']['data']['bill']['public_url'] ?? null;

        if (! $publicUrl) {
            abort(404, 'Factus no devolvió una URL para el documento.');
        }

        $invoice->update(['factus_public_url' => $publicUrl]);

        return redirect()->away($publicUrl);
    }
}
