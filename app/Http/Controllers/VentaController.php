<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    public function __construct(private VentaService $ventaService) {}

    /**
     * Show the new sale screen.
     */
    public function create(): Response
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
}
