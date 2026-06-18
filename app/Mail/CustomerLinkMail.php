<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * E-mail met de ondertekende bevestigingslink voor de cross-device login. Eén klik
 * bevestigt de login-sessie; de wachtende PWA wordt daarna automatisch ingelogd, dus
 * de gebruiker hoeft zijn app niet te verlaten.
 *
 * De ondertekende link wordt op het VERZENDMOMENT gegenereerd (in content()), zodat de
 * TTL pas loopt wanneer de mail echt verstuurd wordt — nooit al verlopen door queue-vertraging.
 */
class CustomerLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailToken,
        public bool $isNew = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isNew
                ? 'Bevestig je e-mailadres voor Koffiebon'
                : 'Je Koffiebon-inloglink',
        );
    }

    public function content(): Content
    {
        $url = URL::temporarySignedRoute(
            'api.auth.confirm',
            now()->addMinutes(config('koffiebon.login_session_minutes')),
            ['token' => $this->emailToken],
        );

        return new Content(
            view: 'mail.customer-link',
            with: [
                'url' => $url,
                'isNew' => $this->isNew,
            ],
        );
    }
}
