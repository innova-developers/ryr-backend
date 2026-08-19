<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * RC-507 — Encuesta post-entrega por email.
 *
 * Hasta acá la encuesta salía sólo por WhatsApp, aunque la V2 contemplaba "correo
 * electrónico o mensaje de WhatsApp". Lleva el mismo link con token que el WhatsApp.
 */
class FeedbackSurveyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $customerName,
        public int $commissionId,
        public string $feedbackUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "¿Cómo fue tu experiencia con el envío #{$this->commissionId}?",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback-survey',
            with: [
                'customerName' => $this->customerName,
                'commissionId' => $this->commissionId,
                'feedbackUrl' => $this->feedbackUrl,
            ],
        );
    }
}
