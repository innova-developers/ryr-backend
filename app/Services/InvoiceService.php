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
    public function __construct(private ArcaService $arcaService) {}

    /**
     * Resuelve los datos fiscales que van a ir al comprobante, ANTES de pedirle el CAE
     * a ARCA. Se expone aparte de emitirFactura() para poder mostrarlos en pantalla y
     * dejar que el operador los corrija: una vez emitido el CAE ya no hay vuelta atrás,
     * sólo anulación.
     *
     * @return array<string, mixed>
     */
    public function resolverDatosFiscales(Customer $customer, int $tipoComprobante, float $total, ?float $ivaRate = null): array
    {
        $ivaRate = $ivaRate ?? 21;
        $total = round($total, 2);
        $neto = round($total / (1 + $ivaRate / 100), 2);

        return [
            'customer_id' => $customer->id,
            'tipo_comprobante' => $tipoComprobante,
            'razon_social' => ($customer->isCompany() && $customer->razon_social)
                ? $customer->razon_social
                : trim($customer->name.' '.($customer->last_name ?? '')),
            'doc_tipo' => $this->resolveDocTipo($customer, $tipoComprobante),
            'doc_numero' => $this->resolveDocNumero($customer),
            'condicion_iva' => $this->resolveCondicionIva($customer),
            'domicilio_cliente' => $customer->address,
            'importe_total' => $total,
            'importe_neto' => $neto,
            'importe_iva' => round($total - $neto, 2),
            'iva_rate' => $ivaRate,
        ];
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

            // Datos fiscales resueltos desde el cliente, salvo que el operador los haya
            // corregido en la pantalla de confirmación (RC-497).
            $resueltos = $this->resolverDatosFiscales($customer, $tipoComprobante, $total, $ivaRate);
            $docTipo = (int) ($datos['doc_tipo'] ?? $resueltos['doc_tipo']);
            $docNumero = (string) ($datos['doc_numero'] ?? $resueltos['doc_numero']);
            $razonSocial = $datos['razon_social'] ?? $resueltos['razon_social'];
            $condicionIva = $datos['condicion_iva'] ?? $resueltos['condicion_iva'];
            $domicilioCliente = $datos['domicilio_cliente'] ?? $resueltos['domicilio_cliente'];

            $fecha = $datos['fecha'] ?? date('Y-m-d');
            $concepto = (int) ($datos['concepto'] ?? 2);

            // Período facturado y vencimiento del pago. Sólo aplican a los conceptos con
            // servicios (2 y 3); para productos ARCA los rechaza. Se resuelven acá, una
            // sola vez, para que lo que se informa y lo que se guarda sea lo mismo.
            $conServicios = in_array($concepto, [2, 3], true);
            $servDesde = $conServicios ? ($datos['fecha_servicio_desde'] ?? $fecha) : null;
            $servHasta = $conServicios ? ($datos['fecha_servicio_hasta'] ?? $servDesde) : null;
            $vtoPago = $conServicios ? ($datos['fecha_vto_pago'] ?? $fecha) : null;

            $resultado = $this->arcaService->emitirComprobante([
                'tipo_comprobante' => $tipoComprobante,
                'importe_total' => $total,
                'importe_neto' => $neto,
                'importe_iva' => $iva,
                'iva_rate' => $ivaRate,
                'doc_tipo' => $docTipo,
                'doc_numero' => $docNumero,
                'fecha' => $fecha,
                'concepto' => $concepto,
                'fecha_servicio_desde' => $servDesde,
                'fecha_servicio_hasta' => $servHasta,
                'fecha_vto_pago' => $vtoPago,
            ]);

            if (! ($resultado['success'] ?? false)) {
                throw new \RuntimeException($resultado['error'] ?? 'Error emitiendo comprobante en ARCA');
            }

            return Invoice::create([
                'customer_id' => $customer->id,
                'commission_id' => $datos['commission_id'] ?? null,
                'current_account_id' => $datos['current_account_id'] ?? null,
                'franchise_id' => $datos['franchise_id'] ?? $customer->franchise_id,
                // El admin de matriz no tiene sucursal: antes de dejarlo en null se
                // intenta la que venga en los datos (la de la comisión) y la del cliente.
                'branch_id' => $datos['branch_id'] ?? $user->branch_id ?? $customer->branch_id,
                'user_id' => $user->id,
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $resultado['punto_venta'],
                'numero_comprobante' => $resultado['numero_comprobante'],
                'fecha_emision' => $fecha,
                'fecha_servicio_desde' => $servDesde,
                'fecha_servicio_hasta' => $servHasta,
                'fecha_vto_pago' => $vtoPago,
                'cae' => $resultado['cae'],
                'cae_vencimiento' => $resultado['cae_vencimiento'],
                'importe_total' => $total,
                'importe_neto' => $neto,
                'importe_iva' => $iva,
                'iva_rate' => $ivaRate,
                'doc_tipo' => $docTipo,
                'doc_numero' => (string) $docNumero,
                'razon_social' => $razonSocial,
                'domicilio_cliente' => $domicilioCliente,
                'condicion_iva' => $condicionIva,
                'concepto' => $datos['concepto'] ?? 2,
                'status' => InvoiceStatus::EMITIDA->value,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides  Datos fiscales corregidos en la confirmación.
     */
    public function facturarComision(Commission $commission, int $tipoComprobante, User $user, array $overrides = []): Invoice
    {
        if ($commission->invoices()->where('status', 'emitida')->exists()) {
            throw new \RuntimeException('Esta comisión ya tiene una factura emitida');
        }

        // El servicio se prestó el día de la comisión, así que ese es el período que
        // se le informa a ARCA y el que se imprime en el comprobante.
        $fechaServicio = $commission->date
            ? \Carbon\Carbon::parse($commission->date)->toDateString()
            : null;

        return $this->emitirFactura($overrides + [
            'customer_id' => $commission->client_id,
            'commission_id' => $commission->id,
            'tipo_comprobante' => $tipoComprobante,
            'importe_total' => $commission->total,
            'franchise_id' => $commission->franchise_id,
            'branch_id' => $commission->branch_id,
            'fecha_servicio_desde' => $fechaServicio,
            'fecha_servicio_hasta' => $fechaServicio,
            'observaciones' => "Factura por comisión #{$commission->id}",
        ], $user);
    }

    /**
     * @param  array<string, mixed>  $overrides  Datos fiscales corregidos en la confirmación.
     */
    public function facturarIngreso(CurrentAccount $payment, int $tipoComprobante, User $user, array $overrides = []): Invoice
    {
        $fechaServicio = $payment->transaction_date
            ? \Carbon\Carbon::parse($payment->transaction_date)->toDateString()
            : null;

        return $this->emitirFactura($overrides + [
            'customer_id' => $payment->customer_id,
            'current_account_id' => $payment->id,
            'tipo_comprobante' => $tipoComprobante,
            'importe_total' => abs($payment->amount),
            'franchise_id' => $payment->franchise_id,
            'fecha_servicio_desde' => $fechaServicio,
            'fecha_servicio_hasta' => $fechaServicio,
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
            'logo_data_uri' => $this->logoDataUri(),
            'qr_data_uri' => $this->qrAfipDataUri($invoice),
            'cae_vencimiento_fmt' => $this->formatFechaAfip($invoice->cae_vencimiento),
            'periodo' => $this->periodoFacturado($invoice),
        ]);

        return $pdf->output();
    }

    /**
     * Período facturado y vencimiento del pago, para el bloque que ARCA exige en los
     * comprobantes de concepto 2 (servicios) y 3 (productos y servicios). Devuelve null
     * en los de productos, donde no corresponde imprimirlo.
     *
     * @return array{desde: string, hasta: string, vto_pago: ?string}|null
     */
    private function periodoFacturado(Invoice $invoice): ?array
    {
        if (! in_array((int) $invoice->concepto, [2, 3], true) || ! $invoice->fecha_servicio_desde) {
            return null;
        }

        return [
            'desde' => $invoice->fecha_servicio_desde->format('d/m/Y'),
            'hasta' => ($invoice->fecha_servicio_hasta ?? $invoice->fecha_servicio_desde)->format('d/m/Y'),
            'vto_pago' => $invoice->fecha_vto_pago?->format('d/m/Y'),
        ];
    }

    /**
     * ARCA devuelve las fechas del CAE en formato Ymd (20260807). Sin esto el
     * comprobante imprimía el número crudo.
     */
    private function formatFechaAfip(?string $fecha): ?string
    {
        if (! $fecha) {
            return null;
        }

        $limpia = preg_replace('/\D/', '', $fecha);

        if (strlen((string) $limpia) !== 8) {
            return $fecha;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Ymd', (string) $limpia);

        return $parsed ? $parsed->format('d/m/Y') : $fecha;
    }

    /**
     * QR obligatorio de ARCA (RG 4892/2020). Codifica la URL de constatación con
     * el payload del comprobante en base64, según el instructivo oficial.
     *
     * Sin CAE no hay nada que constatar (comprobante rechazado o anulado antes de
     * obtenerlo), así que en ese caso no se dibuja.
     */
    private function qrAfipDataUri(Invoice $invoice): ?string
    {
        if (! $invoice->cae) {
            return null;
        }

        $payload = [
            'ver' => 1,
            'fecha' => \Carbon\Carbon::parse($invoice->fecha_emision)->format('Y-m-d'),
            'cuit' => (int) preg_replace('/\D/', '', (string) config('afip.cuit')),
            'ptoVta' => (int) $invoice->punto_venta,
            'tipoCmp' => (int) $invoice->tipo_comprobante,
            'nroCmp' => (int) $invoice->numero_comprobante,
            'importe' => round((float) $invoice->importe_total, 2),
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => (int) $invoice->doc_tipo,
            'nroDocRec' => (int) preg_replace('/\D/', '', (string) $invoice->doc_numero),
            'tipoCodAut' => 'E',
            'codAut' => (int) $invoice->cae,
        ];

        $url = 'https://www.afip.gob.ar/fe/qr/?p='.base64_encode((string) json_encode($payload));

        try {
            $resultado = (new \Endroid\QrCode\Writer\PngWriter)->write(
                new \Endroid\QrCode\QrCode(data: $url, size: 320, margin: 0)
            );

            return $resultado->getDataUri();
        } catch (\Throwable $e) {
            // El QR no puede tumbar la emisión del comprobante.
            \Illuminate\Support\Facades\Log::warning('No se pudo generar el QR de ARCA', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Logo de marca embebido como data URI. Se embebe en vez de referenciarlo por
     * ruta para que DomPDF no dependa de acceso al filesystem ni a la red.
     */
    private function logoDataUri(): ?string
    {
        $path = public_path('logo-ryr.png');

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false
            ? null
            : 'data:image/png;base64,'.base64_encode($contents);
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
