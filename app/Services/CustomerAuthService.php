<?php

namespace App\Services;

use App\Events\LoginConfirmed;
use App\Exceptions\LoginException;
use App\Mail\CustomerLinkMail;
use App\Models\Customer;
use App\Models\LoginSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Passwordless, cross-device klant-authenticatie.
 *
 * Flow:
 *  1. De PWA genereert een geheim `s` en stuurt `channel_hash = sha256(s)`.
 *     loginRequest() maakt een login-sessie (alleen hashes at rest) en mailt een
 *     ondertekende bevestigingslink.
 *  2. De klant klikt de link → confirm(): sessie wordt `confirmed`, e-mail geverifieerd,
 *     en een publiek event `login.{channel_hash}` seint de wachtende PWA in.
 *  3. De PWA wisselt `s` in voor een device-token → claim(): atomisch, single-use.
 *
 * De server kent het platte geheim nooit; inloggen vereist het preimage `s`, dat alleen
 * de initiërende PWA bezit. Een binnenkomende push draagt geen id/token.
 */
class CustomerAuthService
{
    /**
     * Start een login: maak/zoek de klant, leg een sessie vast en mail een bevestigingslink.
     * `channelHash` is sha256(geheim), client-side door de PWA gegenereerd. Lekt nooit of
     * het e-mailadres al bestond.
     */
    public function loginRequest(string $email, string $channelHash): void
    {
        $customer = Customer::firstOrCreate(['email' => Str::lower($email)]);
        $isNew = $customer->wasRecentlyCreated;

        $emailToken = bin2hex(random_bytes(32));

        LoginSession::create([
            'customer_id' => $customer->getKey(),
            'secret_hash' => $channelHash,
            'email_token_hash' => hash('sha256', $emailToken),
            'status' => LoginSession::PENDING,
            'expires_at' => now()->addMinutes(config('koffiebon.login_session_minutes')),
        ]);

        Mail::to($customer->email)->send(new CustomerLinkMail($emailToken, isNew: $isNew));
    }

    /**
     * Verwerk een (signed) e-mailklik: markeer de sessie bevestigd, verifieer de e-mail en
     * sein de wachtende PWA in via het publieke kanaal. Idempotent: opnieuw klikken op een
     * al bevestigde sessie herhaalt enkel het seintje.
     *
     * @return 'confirmed'|'already' status voor de bevestigingspagina
     */
    public function confirm(string $emailToken): string
    {
        $session = LoginSession::where('email_token_hash', hash('sha256', $emailToken))->first();

        if ($session === null) {
            throw LoginException::invalid();
        }

        if ($session->status === LoginSession::PENDING && $session->isExpired()) {
            throw LoginException::expired();
        }

        if ($session->status === LoginSession::CONSUMED) {
            return 'already';
        }

        if ($session->status === LoginSession::PENDING) {
            $session->forceFill([
                'status' => LoginSession::CONFIRMED,
                'confirmed_at' => now(),
            ])->save();

            $customer = $session->customer;
            if (! $customer->hasVerifiedEmail()) {
                $customer->forceFill(['email_verified_at' => now()])->save();
            }
        }

        // Seintje naar de wachtende PWA; payload bevat geen id/token.
        LoginConfirmed::dispatch($session->secret_hash);

        return $session->status === LoginSession::CONFIRMED ? 'confirmed' : 'already';
    }

    /**
     * Wissel het geheim in voor een Sanctum device-token. `secret` is het preimage van
     * `secret_hash`. Pending → de PWA polt door; confirmed → atomisch consumed + token.
     *
     * @return array{customer: Customer, token: string}
     *
     * @throws LoginException
     */
    public function claim(string $secret): array
    {
        $session = LoginSession::where('secret_hash', hash('sha256', $secret))->first();

        if ($session === null) {
            throw LoginException::invalid();
        }

        if ($session->status === LoginSession::CONSUMED) {
            throw LoginException::consumed();
        }

        if ($session->status === LoginSession::PENDING) {
            throw $session->isExpired() ? LoginException::expired() : LoginException::pending();
        }

        // status === CONFIRMED: race-veilig precies één keer consumeren.
        $affected = LoginSession::whereKey($session->getKey())
            ->where('status', LoginSession::CONFIRMED)
            ->update(['status' => LoginSession::CONSUMED, 'consumed_at' => now()]);

        if ($affected === 0) {
            throw LoginException::consumed();
        }

        $customer = $session->customer;
        $token = $customer->createToken('pwa-device', ['customer'])->plainTextToken;

        return ['customer' => $customer, 'token' => $token];
    }
}
