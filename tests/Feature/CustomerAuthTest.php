<?php

use App\Events\LoginConfirmed;
use App\Mail\CustomerLinkMail;
use App\Models\Customer;
use App\Models\LoginSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Maak een pending login-sessie + retourneer het platte geheim en email-token. */
function makeSession(Customer $customer, array $overrides = []): array
{
    $secret = bin2hex(random_bytes(32));
    $emailToken = bin2hex(random_bytes(32));

    $session = LoginSession::create(array_merge([
        'customer_id' => $customer->id,
        'secret_hash' => hash('sha256', $secret),
        'email_token_hash' => hash('sha256', $emailToken),
        'status' => LoginSession::PENDING,
        'expires_at' => now()->addMinutes(30),
    ], $overrides));

    return ['session' => $session, 'secret' => $secret, 'email_token' => $emailToken];
}

it('start een login: maakt de klant + een sessie en mailt een bevestigingslink', function () {
    Mail::fake();

    $secret = bin2hex(random_bytes(32));
    $this->postJson('/api/auth/login-request', [
        'email' => 'nieuw@klant.test',
        'channel_hash' => hash('sha256', $secret),
    ])->assertStatus(202);

    expect(Customer::where('email', 'nieuw@klant.test')->exists())->toBeTrue();
    expect(LoginSession::where('secret_hash', hash('sha256', $secret))->where('status', 'pending')->exists())->toBeTrue();
    Mail::assertQueued(CustomerLinkMail::class);
});

it('antwoordt generiek voor elk adres (lekt niet of het bestond)', function () {
    Mail::fake();
    $secret = bin2hex(random_bytes(32));

    $this->postJson('/api/auth/login-request', [
        'email' => 'onbekend@niemand.test',
        'channel_hash' => hash('sha256', $secret),
    ])->assertStatus(202);

    Mail::assertQueued(CustomerLinkMail::class);
});

it('vereist een geldige channel_hash', function () {
    $this->postJson('/api/auth/login-request', ['email' => 'a@b.test'])
        ->assertStatus(422);

    $this->postJson('/api/auth/login-request', ['email' => 'a@b.test', 'channel_hash' => 'te-kort'])
        ->assertStatus(422);
});

it('genereert de bevestigingslink op het verzendmoment, niet bij het in de wachtrij zetten', function () {
    config(['koffiebon.login_session_minutes' => 600]);
    $customer = Customer::factory()->unverified()->create();
    ['email_token' => $emailToken] = makeSession($customer, ['expires_at' => now()->addMinutes(600)]);

    $mail = new CustomerLinkMail($emailToken);

    // De mail blijft 2 uur in de wachtrij voordat hij verstuurd (gerenderd) wordt.
    $this->travel(2)->hours();

    preg_match('#https?://[^"\s]*/api/auth/confirm/[a-f0-9]+\?[^"\s]+#', $mail->render(), $m);
    $url = html_entity_decode($m[0]);

    // Nog geldig: de TTL begon pas bij het renderen, niet 2 uur eerder.
    $this->get($url)->assertOk();
    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('bevestigt via een signed link, verifieert de e-mail en seint de PWA in', function () {
    Event::fake([LoginConfirmed::class]);
    $customer = Customer::factory()->unverified()->create();
    ['session' => $session, 'email_token' => $emailToken] = makeSession($customer);

    $url = URL::temporarySignedRoute('api.auth.confirm', now()->addHour(), ['token' => $emailToken]);
    $this->get($url)->assertOk();

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($session->fresh()->status)->toBe('confirmed');

    Event::assertDispatched(LoginConfirmed::class, fn (LoginConfirmed $e) => $e->secretHash === $session->secret_hash);
});

it('weigert een confirm-link zonder geldige signature', function () {
    $customer = Customer::factory()->unverified()->create();
    ['email_token' => $emailToken] = makeSession($customer);

    $this->get("/api/auth/confirm/{$emailToken}")->assertForbidden();
    expect($customer->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('geeft login_pending zolang de sessie nog niet bevestigd is', function () {
    $customer = Customer::factory()->create();
    ['secret' => $secret] = makeSession($customer);

    $this->postJson('/api/auth/claim', ['secret' => $secret])
        ->assertStatus(409)
        ->assertJsonPath('code', 'login_pending');
});

it('wisselt het geheim in voor een werkend device-token na bevestiging', function () {
    $customer = Customer::factory()->create();
    ['session' => $session, 'secret' => $secret] = makeSession($customer, [
        'status' => LoginSession::CONFIRMED,
        'confirmed_at' => now(),
    ]);

    $token = $this->postJson('/api/auth/claim', ['secret' => $secret])
        ->assertOk()
        ->json('device_token');

    expect($token)->toBeString();
    expect($session->fresh()->status)->toBe('consumed');

    $this->withToken($token)->getJson('/api/pwa/me')
        ->assertOk()
        ->assertJsonPath('id', $customer->id);
});

it('staat een claim maar één keer toe (single-use)', function () {
    $customer = Customer::factory()->create();
    ['secret' => $secret] = makeSession($customer, [
        'status' => LoginSession::CONFIRMED,
        'confirmed_at' => now(),
    ]);

    $this->postJson('/api/auth/claim', ['secret' => $secret])->assertOk();
    $this->postJson('/api/auth/claim', ['secret' => $secret])
        ->assertStatus(409)
        ->assertJsonPath('code', 'login_consumed');
});

it('kan niet inloggen met enkel de channel_hash (geen preimage)', function () {
    $customer = Customer::factory()->create();
    ['session' => $session, 'secret' => $secret] = makeSession($customer, [
        'status' => LoginSession::CONFIRMED,
        'confirmed_at' => now(),
    ]);

    // Een aanvaller kent hooguit de kanaalnaam (= secret_hash), niet het preimage.
    $this->postJson('/api/auth/claim', ['secret' => $session->secret_hash])
        ->assertStatus(404)
        ->assertJsonPath('code', 'login_invalid');

    // De echte sessie is nog bruikbaar.
    expect($session->fresh()->status)->toBe('confirmed');
});
