<?php

namespace App\Services;

use App\Exceptions\TokenException;
use App\Mail\CustomerLinkMail;
use App\Models\Customer;
use App\Models\DeviceClaim;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Passwordless klant-authenticatie: registratie, e-mailverificatie, magic-link-herstel
 * en het inwisselen van een eenmalige device-claim-code voor een Sanctum device-token.
 */
class CustomerAuthService
{
    /**
     * Maak/zoek een klant op e-mail en mail een ondertekende verificatielink.
     * Lekt nooit of het e-mailadres al bestond.
     */
    public function register(string $email): Customer
    {
        $customer = Customer::firstOrCreate(
            ['email' => Str::lower($email)],
        );

        Mail::to($customer->email)->send(
            new CustomerLinkMail($this->verificationUrl($customer), isRecovery: false),
        );

        return $customer;
    }

    /**
     * Herstel: mail opnieuw een ondertekende link, maar alleen als de klant bestaat.
     * Retourneert null als er geen klant is (stil, om enumeratie te voorkomen).
     */
    public function sendMagicLink(string $email): ?Customer
    {
        $customer = Customer::where('email', Str::lower($email))->first();

        if ($customer === null) {
            return null;
        }

        Mail::to($customer->email)->send(
            new CustomerLinkMail($this->verificationUrl($customer), isRecovery: true),
        );

        return $customer;
    }

    /**
     * Verwerk een geldige (signed) verificatie: markeer geverifieerd en maak een
     * eenmalige device-claim-code. Retourneert de redirect-URL naar de PWA met de code.
     */
    public function verifyAndIssueClaim(Customer $customer): string
    {
        if (! $customer->hasVerifiedEmail()) {
            $customer->forceFill(['email_verified_at' => now()])->save();
        }

        $code = $this->createDeviceClaim($customer);

        return rtrim((string) config('koffiebon.frontend_url'), '/').'/claim?code='.$code;
    }

    /**
     * Wissel een device-claim-code in voor een Sanctum device-token.
     *
     * @return array{customer: Customer, token: string}
     *
     * @throws TokenException als de code onbekend, verlopen of al gebruikt is.
     */
    public function claim(string $code): array
    {
        $claim = DeviceClaim::where('code_hash', hash('sha256', $code))->first();

        if ($claim === null) {
            throw TokenException::invalid();
        }

        if (! $claim->isUsable()) {
            throw $claim->consumed_at !== null ? TokenException::alreadyUsed() : TokenException::expired();
        }

        $affected = DeviceClaim::whereKey($claim->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($affected === 0) {
            throw TokenException::alreadyUsed();
        }

        $customer = $claim->customer;
        $token = $customer->createToken('pwa-device', ['customer'])->plainTextToken;

        return ['customer' => $customer, 'token' => $token];
    }

    private function verificationUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute(
            'api.auth.verify',
            now()->addMinutes(config('koffiebon.verification_link_minutes')),
            ['customer' => $customer->getKey()],
        );
    }

    private function createDeviceClaim(Customer $customer): string
    {
        $code = bin2hex(random_bytes(16));

        DeviceClaim::create([
            'customer_id' => $customer->getKey(),
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds(config('koffiebon.device_claim_ttl')),
        ]);

        return $code;
    }
}
