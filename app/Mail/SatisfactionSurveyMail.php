<?php

namespace App\Mail;

use App\SatisfactionSurvey;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SatisfactionSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Commission $commission,
        public Customer $customer,
        public SatisfactionSurvey $survey
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Encuesta de Satisfacción - Envío #{$this->commission->id}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $surveyUrl = config('app.url', 'http://localhost:8000') . '/survey/' . $this->survey->token;

        return new Content(
            view: 'emails.satisfaction-survey',
            with: [
                'commission' => $this->commission,
                'customer' => $this->customer,
                'survey' => $this->survey,
                'surveyUrl' => $surveyUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
