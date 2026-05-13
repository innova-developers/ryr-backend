<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\InvoiceStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class IvaReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $user = $request->user();

        $query = $this->baseQuery($dateFrom, $dateTo, $user, $request->input('franchise_id'));

        $totalIva = (clone $query)->sum('importe_iva');
        $totalNeto = (clone $query)->sum('importe_neto');
        $totalFinal = (clone $query)->sum('importe_total');
        $invoiceCount = (clone $query)->count();

        $byType = (clone $query)
            ->select(
                'tipo_comprobante',
                DB::raw('SUM(importe_iva) as total_iva'),
                DB::raw('SUM(importe_neto) as total_neto'),
                DB::raw('SUM(importe_total) as total_final'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('tipo_comprobante')
            ->get()
            ->map(function ($item) {
                $type = InvoiceType::tryFrom($item->tipo_comprobante);
                return [
                    'tipo_comprobante' => $item->tipo_comprobante,
                    'label' => $type?->label() ?? 'Desconocido',
                    'short_label' => $type?->shortLabel() ?? '?',
                    'total_iva' => round($item->total_iva, 2),
                    'total_neto' => round($item->total_neto, 2),
                    'total_final' => round($item->total_final, 2),
                    'count' => $item->count,
                ];
            });

        $byFranchise = [];
        if ($user->role !== UserRole::ADMIN_FRANQUICIA) {
            $byFranchise = (clone $query)
                ->join('franchises', 'invoices.franchise_id', '=', 'franchises.id')
                ->select(
                    'franchises.name as franchise_name',
                    'franchises.id as franchise_id',
                    DB::raw('SUM(invoices.importe_iva) as total_iva'),
                    DB::raw('SUM(invoices.importe_neto) as total_neto'),
                    DB::raw('SUM(invoices.importe_total) as total_final'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('franchises.id', 'franchises.name')
                ->get()
                ->map(fn ($item) => [
                    'franchise_id' => $item->franchise_id,
                    'franchise_name' => $item->franchise_name,
                    'total_iva' => round($item->total_iva, 2),
                    'total_neto' => round($item->total_neto, 2),
                    'total_final' => round($item->total_final, 2),
                    'count' => $item->count,
                ]);
        }

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_iva' => round($totalIva, 2),
            'total_neto' => round($totalNeto, 2),
            'total_final' => round($totalFinal, 2),
            'invoice_count' => $invoiceCount,
            'by_type' => $byType,
            'by_franchise' => $byFranchise,
        ]);
    }

    public function detail(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $user = $request->user();

        $query = $this->baseQuery($dateFrom, $dateTo, $user, $request->input('franchise_id'));

        if ($request->has('tipo_comprobante')) {
            $query->where('invoices.tipo_comprobante', $request->input('tipo_comprobante'));
        }

        $invoices = $query
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('franchises', 'invoices.franchise_id', '=', 'franchises.id')
            ->select(
                'invoices.id',
                'invoices.tipo_comprobante',
                'invoices.punto_venta',
                'invoices.numero_comprobante',
                'invoices.fecha_emision',
                'invoices.razon_social',
                'invoices.importe_neto',
                'invoices.importe_iva',
                'invoices.importe_total',
                'invoices.cae',
                'customers.name as customer_name',
                'franchises.name as franchise_name'
            )
            ->orderBy('invoices.fecha_emision', 'desc')
            ->get()
            ->map(function ($inv) {
                $type = InvoiceType::tryFrom($inv->tipo_comprobante);
                return [
                    'id' => $inv->id,
                    'type_label' => $type?->shortLabel() ?? '?',
                    'numero' => str_pad($inv->punto_venta, 5, '0', STR_PAD_LEFT) . '-' . str_pad($inv->numero_comprobante, 8, '0', STR_PAD_LEFT),
                    'fecha' => $inv->fecha_emision,
                    'razon_social' => $inv->razon_social,
                    'customer_name' => $inv->customer_name,
                    'importe_neto' => round($inv->importe_neto, 2),
                    'importe_iva' => round($inv->importe_iva, 2),
                    'importe_total' => round($inv->importe_total, 2),
                    'cae' => $inv->cae,
                    'franchise_name' => $inv->franchise_name,
                ];
            });

        return response()->json(['invoices' => $invoices]);
    }

    private function baseQuery(string $dateFrom, string $dateTo, $user, ?string $franchiseId)
    {
        $query = Invoice::where('invoices.status', InvoiceStatus::EMITIDA)
            ->whereBetween('invoices.fecha_emision', [$dateFrom, $dateTo])
            ->whereNull('invoices.deleted_at');

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $query->where('invoices.franchise_id', $user->franchise_id);
        } elseif ($franchiseId) {
            $query->where('invoices.franchise_id', $franchiseId);
        }

        return $query;
    }
}
