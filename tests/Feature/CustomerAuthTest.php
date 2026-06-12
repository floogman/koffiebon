<?php

use App\Mail\CustomerLinkMail;
use App\Models\Customer;
use App\Models\DeviceClaim;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('registreert een klant en mailt een verificatielink', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', ['email' => 'nieuw@klant.test'])
        ->assertStatus(202);

    expect(Customer::where('email', 'nieuw@klant.test')->exists())->toBeTrue();
    Mail::assertQueued(CustomerLinkMail::class);
});

it('verifieert via een signed link en redirect met een claim-code', function () {
    $customer = Customer::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('api.auth.verify', now()->addHour(), ['customer' => $customer->id]);

    $response = $this->get($url);
    $response->assertRedirect();

    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($response->headers->get('Location'))->toContain('/claim?code=');
    expect(DeviceClaim::where('customer_id', $customer->id)->exists())->toBeTrue();
});

it('weigert een verify-link zonder geldige signature', function () {
    $customer = Customer::factory()->unverified()->create();

    $this->get("/api/auth/verify/{$customer->id}")->assertForbidden();
    expect($customer->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('wisselt een claim-code in voor een werkend device-token', function () {
    $customer = Customer::factory()->create();
    $code = bin2hex(random_bytes(16));
    DeviceClaim::create([
        'customer_id' => $customer->id,
        'code_hash' => hash('sha256', $code),
        'expires_at' => now()->addMinutes(10),
    ]);

    $token = $this->postJson('/api/auth/claim', ['code' => $code])
        ->assertOk()
        ->json('device_token');

    expect($token)->toBeString();

    // Het token werkt op een beschermd PWA-endpoint.
    $this->withToken($token)->getJson('/api/pwa/me')
        ->assertOk()
        ->assertJsonPath('id', $customer->id);
});

it('staat een claim-code maar één keer toe (single-use)', function () {
    $customer = Customer::factory()->create();
    $code = bin2hex(random_bytes(16));
    DeviceClaim::create([
        'customer_id' => $customer->id,
        'code_hash' => hash('sha256', $code),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/claim', ['code' => $code])->assertOk();
    $this->postJson('/api/auth/claim', ['code' => $code])->assertStatus(409);
});

it('lekt niet of een e-mailadres bestaat bij magic-link', function () {
    Mail::fake();

    $this->postJson('/api/auth/magic-link', ['email' => 'onbekend@niemand.test'])
        ->assertStatus(202);

    Mail::assertNothingQueued();
});
