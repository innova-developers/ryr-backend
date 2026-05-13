<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePdfMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $pdfContent,
        public string $invoiceLabel,
        public string $invoiceNumber,
        public string $customerName,
        public string $emisorName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->invoiceLabel} {$this->invoiceNumber} — {$this->emisorName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-pdf',
            with: [
                'invoiceLabel' => $this->invoiceLabel,
                'invoiceNumber' => $this->invoiceNumber,
                'customerName' => $this->customerName,
                'emisorName' => $this->emisorName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $filename = str_replace(' ', '_', "{$this->invoiceLabel}_{$this->invoiceNumber}.pdf");

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
