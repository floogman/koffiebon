<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * E-mail met een ondertekende verificatie-/inloglink naar de PWA.
 * Wordt gebruikt voor zowel eerste registratie als het inloggen met je e-mailadres.
 *
 * De ondertekende link wordt op het VERZENDMOMENT gegenereerd (in content()),
 * niet bij het in de wachtrij zetten. Zo begint de TTL pas te lopen wanneer de
 * mail echt verstuurd wordt en arriveert de link nooit al verlopen door queue-vertraging.
 */
class CustomerLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public bool $isLogin = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isLogin
                ? 'Je Koffiebon-inloglink'
                : 'Bevestig je e-mailadres voor Koffiebon',
        );
    }

    public function content(): Content
    {
        $url = URL::temporarySignedRoute(
            'api.auth.verify',
            now()->addMinutes(config('koffiebon.verification_link_minutes')),
            ['customer' => $this->customer->getKey()],
        );

        return new Content(
            view: 'mail.customer-link',
            with: [
                'url' => $url,
                'isLogin' => $this->isLogin,
            ],
        );
    }
}
