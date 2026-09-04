<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\FactusRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'kpis' => $this->kpis(),
            'statusDistribution' => $this->statusDistribution(),
            'factusStats' => $this->factusStats(),
            'recentInvoices' => $this->recentInvoices(),
            'topProducts' => $this->topProducts(),
        ]);
    }

    /**
     * Calculate the main KPIs from validated invoices.
     *
     * @return array{totalFacturado: float, facturasValidadas: int, facturasRechazadas: int, ticketPromedio: float}
     */
    private function kpis(): array
    {
        $validatedTotals = Invoice::query()
            ->where('status', InvoiceStatus::Validated)
            ->selectRaw('COALESCE(SUM(total), 0) as total_facturado')
            ->selectRaw('COALESCE(AVG(total), 0) as ticket_promedio')
            ->selectRaw('COUNT(*) as total_validadas')
            ->first();

        $rejectedCount = Invoice::query()
            ->where('status', InvoiceStatus::Rejected)
            ->count();

        return [
            'totalFacturado' => (float) $validatedTotals->total_facturado,
            'facturasValidadas' => (int) $validatedTotals->total_validadas,
            'facturasRechazadas' => $rejectedCount,
            'ticketPromedio' => (float) $validatedTotals->ticket_promedio,
        ];
    }

    /**
     * Count invoices grouped by status.
     *
     * @return array{draft: int, pending: int, validated: int, rejected: int}
     */
    private function statusDistribution(): array
    {
        $counts = Invoice::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'draft' => $counts[InvoiceStatus::Draft->value] ?? 0,
            'pending' => $counts[InvoiceStatus::Pending->value] ?? 0,
            'validated' => $counts[InvoiceStatus::Validated->value] ?? 0,
            'rejected' => $counts[InvoiceStatus::Rejected->value] ?? 0,
        ];
    }

    /**
     * Calculate Factus API success rate from the audit trail.
     *
     * @return array{successful: int, total: int, successRate: float}
     */
    private function factusStats(): array
    {
        $total = FactusRequest::count();

        if ($total === 0) {
            return ['successful' => 0, 'total' => 0, 'successRate' => 0.0];
        }

        $successful = FactusRequest::where('success', true)->count();

        return [
            'successful' => $successful,
            'total' => $total,
            'successRate' => round(($successful / $total) * 100, 1),
        ];
    }

    /**
     * Get the 5 most recent invoices with their customer.
     *
     * @return array<int, array{id: int, displayNumber: string, customerName: string, total: float, status: string, statusLabel: string, createdAt: string, invoiceId: int}>
     */
    private function recentInvoices(): array
    {
        return Invoice::query()
            ->with('customer:id,company,trade_name,names')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'displayNumber' => $invoice->factus_number ?? $invoice->reference_code,
                'customerName' => $invoice->customer->company
                    ?? $invoice->customer->trade_name
                    ?? $invoice->customer->names
                    ?? 'Sin cliente',
                'total' => (float) $invoice->total,
                'status' => $invoice->status->value,
                'statusLabel' => $this->statusLabel($invoice->status),
                'createdAt' => $invoice->created_at->format('d/m/Y H:i'),
                'invoiceId' => $invoice->id,
            ])
            ->toArray();
    }

    /**
     * Get the top 3 products by quantity sold in validated invoices.
     *
     * @return array<int, array{productName: string, quantity: int}>
     */
    private function topProducts(): array
    {
        return InvoiceItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->whereHas('invoice', fn ($query) => $query->where('status', InvoiceStatus::Validated))
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(3)
            ->with('product:id,name')
            ->get()
            ->map(fn (InvoiceItem $item): array => [
                'productName' => $item->product?->name ?? 'Producto eliminado',
                'quantity' => (int) $item->total_quantity,
            ])
            ->toArray();
    }

    /**
     * Map an InvoiceStatus to a human-readable Spanish label.
     */
    private function statusLabel(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Draft => 'Borrador',
            InvoiceStatus::Pending => 'Pendiente',
            InvoiceStatus::Validated => 'Validada',
            InvoiceStatus::Rejected => 'Rechazada',
            InvoiceStatus::Cancelled => 'Cancelada',
        };
    }
}
