<?php

namespace App\Mail;

use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommissionStatusChangedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Commission $commission,
        public Customer $customer,
        public string $previousStatus,
        public string $newStatus,
        public ?string $details = null
    ) {
    }

    public function envelope(): Envelope
    {
        $statusLabels = [
            'ACEPTADO' => 'Aceptado',
            'RETIRADO' => 'Retirado para Transporte',
            'RETIRADO_SUCURSAL' => 'Retirado en Sucursal',
            'ENTREGADO' => 'Entregado Exitosamente',
            'CANCELADO' => 'Cancelado',
            'PAGADO' => 'Pago Confirmado',
            'PAGO_CONFIRMADO' => 'Pago Confirmado',
            'CADETE_ASIGNADO' => 'Cadete Asignado',
            'ENCOMIENDA_RETIRADA' => 'Encomienda Retirada',
        ];

        $statusLabel = $statusLabels[$this->newStatus] ?? $this->newStatus;

        return new Envelope(
            subject: "Tu envío #{$this->commission->id} - {$statusLabel}",
        );
    }

    public function content(): Content
    {
        $statusMessages = [
            'ACEPTADO' => 'Tu presupuesto ha sido aceptado y tu envío está siendo preparado.',
            'RETIRADO' => 'Tu envío ha sido retirado y está en camino hacia su destino.',
            'RETIRADO_SUCURSAL' => 'Tu envío ha sido retirado en sucursal y está listo para ser retirado.',
            'ENTREGADO' => '¡Tu envío ha sido entregado exitosamente!',
            'CANCELADO' => 'Tu comisión ha sido cancelada.',
            'PAGADO' => 'El pago de tu comisión ha sido confirmado.',
            'PAGO_CONFIRMADO' => 'El pago de tu comisión ha sido confirmado.',
            'CADETE_ASIGNADO' => 'Se ha asignado un cadete a tu envío.',
            'ENCOMIENDA_RETIRADA' => 'Tu encomienda ha sido retirada y está en tránsito.',
        ];

        $statusMessage = $statusMessages[$this->newStatus] ?? "El estado de tu envío ha cambiado a: {$this->newStatus}";

        return new Content(
            view: 'emails.commission-status-changed',
            with: [
                'commission' => $this->commission,
                'customer' => $this->customer,
                'previousStatus' => $this->previousStatus,
                'newStatus' => $this->newStatus,
                'statusMessage' => $statusMessage,
                'details' => $this->details,
                'trackingUrl' => rtrim(config('app.frontend_url'), '/') . "/tracking/{$this->commission->id}",
            ],
        );
    }
}
