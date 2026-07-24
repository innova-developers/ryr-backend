<?php

namespace App\Services;

use App\Shared\Enums\InvoiceStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Invoice;
use App\Shared\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private ArcaService $arcaService)
    {
    }

    public function emitirFactura(array $datos, User $user): Invoice
    {
        return DB::transaction(function () use ($datos, $user) {
            $customer = Customer::findOrFail($datos['customer_id']);
            $tipoComprobante = (int) $datos['tipo_comprobante'];
            $total = round((float) $datos['importe_total'], 2);
            $ivaRate = (float) ($datos['iva_rate'] ?? 21);
            $neto = round($total / (1 + $ivaRate / 100), 2);
            $iva = round($total - $neto, 2);

            $docTipo = $this->resolveDocTipo($customer, $tipoComprobante);
            $docNumero = $this->resolveDocNumero($customer);

            $resultado = $this->arcaService->emitirComprobante([
                'tipo_comprobante' => $tipoComprobante,
                'importe_total' => $total,
                'importe_neto' => $neto,
                'importe_iva' => $iva,
                'iva_rate' => $ivaRate,
                'doc_tipo' => $docTipo,
                'doc_numero' => $docNumero,
                'fecha' => $datos['fecha'] ?? date('Y-m-d'),
                'concepto' => $datos['concepto'] ?? 2,
            ]);

            if (! ($resultado['success'] ?? false)) {
                throw new \RuntimeException($resultado['error'] ?? 'Error emitiendo comprobante en ARCA');
            }

            return Invoice::create([
                'customer_id' => $customer->id,
                'commission_id' => $datos['commission_id'] ?? null,
                'current_account_id' => $datos['current_account_id'] ?? null,
                'franchise_id' => $datos['franchise_id'] ?? $customer->franchise_id,
                'branch_id' => $datos['branch_id'] ?? $user->branch_id,
                'user_id' => $user->id,
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $resultado['punto_venta'],
                'numero_comprobante' => $resultado['numero_comprobante'],
                'fecha_emision' => $datos['fecha'] ?? now()->toDateString(),
                'cae' => $resultado['cae'],
                'cae_vencimiento' => $resultado['cae_vencimiento'],
                'importe_total' => $total,
                'importe_neto' => $neto,
                'importe_iva' => $iva,
                'iva_rate' => $ivaRate,
                'doc_tipo' => $docTipo,
                'doc_numero' => (string) $docNumero,
                'razon_social' => ($customer->isCompany() && $customer->razon_social)
                    ? $customer->razon_social
                    : trim($customer->name . ' ' . ($customer->last_name ?? '')),
                'domicilio_cliente' => $customer->address,
                'condicion_iva' => $this->resolveCondicionIva($customer),
                'concepto' => $datos['concepto'] ?? 2,
                'status' => InvoiceStatus::EMITIDA->value,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);
        });
    }

    public function facturarComision(Commission $commission, int $tipoComprobante, User $user): Invoice
    {
        if ($commission->invoices()->where('status', 'emitida')->exists()) {
            throw new \RuntimeException('Esta comisión ya tiene una factura emitida');
        }

        return $this->emitirFactura([
            'customer_id' => $commission->client_id,
            'commission_id' => $commission->id,
            'tipo_comprobante' => $tipoComprobante,
            'importe_total' => $commission->total,
            'franchise_id' => $commission->franchise_id,
            'branch_id' => $commission->branch_id,
            'observaciones' => "Factura por comisión #{$commission->id}",
        ], $user);
    }

    public function facturarIngreso(CurrentAccount $payment, int $tipoComprobante, User $user): Invoice
    {
        return $this->emitirFactura([
            'customer_id' => $payment->customer_id,
            'current_account_id' => $payment->id,
            'tipo_comprobante' => $tipoComprobante,
            'importe_total' => abs($payment->amount),
            'franchise_id' => $payment->franchise_id,
            'observaciones' => "Factura por pago/ingreso #{$payment->id}",
        ], $user);
    }

    public function anularFactura(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => InvoiceStatus::ANULADA->value]);

        return $invoice->fresh();
    }

    public function getInvoicesByCustomer(int $customerId, array $filters = [])
    {
        $query = Invoice::where('customer_id', $customerId)
            ->with(['commission', 'currentAccount', 'user'])
            ->orderByDesc('fecha_emision');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['tipo_comprobante'])) {
            $query->where('tipo_comprobante', $filters['tipo_comprobante']);
        }

        return $query->get();
    }

    public function getInvoices(array $filters = [], ?int $franchiseId = null)
    {
        $query = Invoice::with(['customer', 'commission', 'currentAccount', 'user'])
            ->orderByDesc('fecha_emision');

        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['tipo_comprobante'])) {
            $query->where('tipo_comprobante', $filters['tipo_comprobante']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('fecha_emision', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('fecha_emision', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('razon_social', 'like', "%{$s}%")
                  ->orWhere('cae', 'like', "%{$s}%")
                  ->orWhere('numero_comprobante', 'like', "%{$s}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function getInvoicePdfData(Invoice $invoice): array
    {
        $invoice->load(['customer', 'commission', 'user']);
        $invoiceType = InvoiceType::tryFrom($invoice->tipo_comprobante);

        return [
            'invoice' => $invoice,
            'invoiceType' => $invoiceType,
            'letter' => $invoiceType?->letter() ?? '?',
            'label' => $invoiceType?->label() ?? 'Comprobante',
            'formatted_number' => $invoice->formatted_number,
            'emisor' => [
                'razon_social' => config('afip.razon_social'),
                'cuit' => config('afip.cuit'),
                'domicilio' => config('afip.domicilio'),
                'condicion_iva' => config('afip.condicion_iva'),
            ],
        ];
    }

    public function generatePdf(Invoice $invoice): string
    {
        $data = $this->getInvoicePdfData($invoice);

        $docLabel = match ($invoice->doc_tipo) {
            80 => 'C.U.I.T.',
            96 => 'D.N.I.',
            default => 'Consumidor Final',
        };

        $description = $invoice->commission_id
            ? "Servicio de comisiones #{$invoice->commission_id}"
            : ($invoice->current_account_id
                ? "Pago/Ingreso #{$invoice->current_account_id}"
                : ($invoice->observaciones ?: 'Servicios'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
            ...$data,
            'doc_label' => $docLabel,
            'description' => $description,
        ]);

        return $pdf->output();
    }

    private function resolveDocTipo(Customer $customer, int $tipoComprobante): int
    {
        $invoiceType = InvoiceType::tryFrom($tipoComprobante);
        if (! $invoiceType) {
            return 99;
        }

        if ($invoiceType->letter() === 'A') {
            return 80; // CUIT
        }

        // Empresa se identifica por CUIT.
        if ($customer->isCompany() && $customer->cuit) {
            return 80; // CUIT
        }

        if ($customer->dni) {
            return 96; // DNI
        }

        return 99; // Consumidor Final
    }

    private function resolveDocNumero(Customer $customer): string
    {
        // Empresa (o cliente sin DNI con CUIT cargado) factura con CUIT.
        if ($customer->cuit && ($customer->isCompany() || ! $customer->dni)) {
            return preg_replace('/\D/', '', $customer->cuit);
        }

        return (string) ($customer->dni ?? 0);
    }

    private function resolveCondicionIva(Customer $customer): string
    {
        $ivaStatus = $customer->iva_status ?? 'auto';

        return match ($ivaStatus) {
            'always' => 'IVA Responsable Inscripto',
            'exempt' => 'IVA Exento',
            default => 'Consumidor Final',
        };
    }
}
