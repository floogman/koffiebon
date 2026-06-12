<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail met een ondertekende verificatie-/herstellink naar de PWA.
 * Wordt gebruikt voor zowel eerste registratie als magic-link-herstel.
 */
class CustomerLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public bool $isRecovery = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isRecovery
                ? 'Je Koffiebon-kaarten herstellen'
                : 'Bevestig je e-mailadres voor Koffiebon',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-link',
        );
    }
}
