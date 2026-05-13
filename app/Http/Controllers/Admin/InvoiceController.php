<?php

namespace App\Http\Controllers\Admin;

use App\Mail\InvoicePdfMail;
use App\Services\ArcaService;
use App\Services\InvoiceService;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\InvoiceType;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private ArcaService $arcaService,
        private WhatsAppService $whatsAppService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $franchiseId = $request->user()->franchise_id;
        $filters = $request->only([
            'customer_id', 'status', 'tipo_comprobante',
            'date_from', 'date_to', 'search', 'per_page',
        ]);

        $invoices = $this->invoiceService->getInvoices($filters, $franchiseId);

        return response()->json($invoices);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'commission', 'currentAccount', 'user']);

        return response()->json($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tipo_comprobante' => 'required|integer',
            'importe_total' => 'required|numeric|min:0.01',
            'iva_rate' => 'nullable|numeric',
            'commission_id' => 'nullable|exists:commissions,id',
            'current_account_id' => 'nullable|exists:current_accounts,id',
            'observaciones' => 'nullable|string|max:500',
            'fecha' => 'nullable|date',
            'concepto' => 'nullable|integer|in:1,2,3',
        ]);

        $validated['franchise_id'] = $request->user()->franchise_id;
        $validated['branch_id'] = $request->user()->branch_id;

        $invoice = $this->invoiceService->emitirFactura($validated, $request->user());

        return response()->json($invoice, 201);
    }

    public function facturarComision(Request $request, Commission $commission): JsonResponse
    {
        if ($commission->status !== CommissionStatus::PAGO_VALIDACION) {
            return response()->json([
                'error' => 'Solo se pueden facturar comisiones en estado PAGO_VALIDACION',
            ], 422);
        }

        $tipoComprobante = $request->input('tipo_comprobante', InvoiceType::FACTURA_B->value);

        $invoice = $this->invoiceService->facturarComision($commission, $tipoComprobante, $request->user());

        return response()->json($invoice, 201);
    }

    public function facturarIngreso(Request $request, CurrentAccount $payment): JsonResponse
    {
        if ($payment->type !== 'credit' || $payment->status->value !== 'OK') {
            return response()->json([
                'error' => 'Solo se pueden facturar ingresos confirmados (crédito, estado OK)',
            ], 422);
        }

        $tipoComprobante = $request->input('tipo_comprobante', InvoiceType::FACTURA_B->value);

        $invoice = $this->invoiceService->facturarIngreso($payment, $tipoComprobante, $request->user());

        return response()->json($invoice, 201);
    }

    public function customerInvoices(Request $request, int $customerId): JsonResponse
    {
        $filters = $request->only(['status', 'tipo_comprobante']);
        $invoices = $this->invoiceService->getInvoicesByCustomer($customerId, $filters);

        return response()->json($invoices);
    }

    public function anular(Invoice $invoice): JsonResponse
    {
        if ($invoice->status === \App\Shared\Enums\InvoiceStatus::ANULADA) {
            return response()->json(['error' => 'La factura ya está anulada'], 422);
        }

        $invoice = $this->invoiceService->anularFactura($invoice);

        return response()->json($invoice);
    }

    public function pdf(Invoice $invoice): JsonResponse
    {
        $data = $this->invoiceService->getInvoicePdfData($invoice);

        return response()->json($data);
    }

    public function tiposComprobante(): JsonResponse
    {
        $tipos = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'letter' => $case->letter(),
            'shortLabel' => $case->shortLabel(),
        ], InvoiceType::cases());

        return response()->json($tipos);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $pdfContent = $this->invoiceService->generatePdf($invoice);
        $data = $this->invoiceService->getInvoicePdfData($invoice);
        $filename = str_replace(' ', '_', "{$data['label']}_{$data['formatted_number']}.pdf");

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function sendEmail(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $pdfContent = $this->invoiceService->generatePdf($invoice);
        $pdfData = $this->invoiceService->getInvoicePdfData($invoice);

        Mail::to($validated['email'])->send(new InvoicePdfMail(
            pdfContent: $pdfContent,
            invoiceLabel: $pdfData['label'],
            invoiceNumber: $pdfData['formatted_number'],
            customerName: $invoice->razon_social ?? $invoice->customer?->name ?? 'Cliente',
            emisorName: $pdfData['emisor']['razon_social'],
        ));

        return response()->json(['message' => 'Email enviado correctamente']);
    }

    public function sendWhatsApp(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:8',
            'message' => 'nullable|string|max:1000',
        ]);

        $pdfContent = $this->invoiceService->generatePdf($invoice);
        $pdfData = $this->invoiceService->getInvoicePdfData($invoice);
        $filename = str_replace(' ', '_', "{$pdfData['label']}_{$pdfData['formatted_number']}.pdf");

        $sent = $this->whatsAppService->sendFileByUpload(
            phone: $validated['phone'],
            fileContent: $pdfContent,
            fileName: $filename,
            caption: $validated['message'] ?? null,
        );

        if (! $sent) {
            return response()->json([
                'error' => $this->whatsAppService->getLastError() ?: 'Error al enviar WhatsApp',
            ], 500);
        }

        return response()->json(['message' => 'WhatsApp enviado correctamente']);
    }

    public function consultarCuit(Request $request): JsonResponse
    {
        $cuit = $request->validate(['cuit' => 'required|string|min:11'])['cuit'];

        $result = $this->arcaService->consultarContribuyente($cuit);

        if (! $result) {
            return response()->json(['error' => 'No se encontró el contribuyente'], 404);
        }

        return response()->json($result);
    }
}
