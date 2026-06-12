<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\QrToken;
use App\Models\StaffUser;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->merchant = Merchant::factory()->create();
    $this->location = Location::factory()->for($this->merchant)->create();
    $this->staff = StaffUser::factory()->for($this->merchant)->for($this->location)->create();
    $this->product = CardProduct::factory()->for($this->merchant)->create([
        'cups_total' => 12, 'cups_paid' => 10, 'price_per_cup_cents' => 300,
    ]);
    $this->customer = Customer::factory()->create(); // geverifieerd
});

function asCustomer($customer)
{
    Sanctum::actingAs($customer, ['customer']);
}

function asStaff($staff)
{
    Sanctum::actingAs($staff, ['staff']);
}

it('doorloopt de hele flow A→C: identify, kaart kopen, verzilveren', function () {
    // B1 — klant toont identify-QR
    asCustomer($this->customer);
    $identifyNonce = $this->postJson('/api/pwa/tokens', ['purpose' => 'identify'])
        ->assertOk()->json('nonce');

    // B2 — balie scant identify-token
    asStaff($this->staff);
    $scan = $this->postJson('/api/staff/scan', ['nonce' => $identifyNonce])->assertOk();
    $scan->assertJsonPath('type', 'identify')
        ->assertJsonPath('customer.id', $this->customer->id);

    // B3 — balie maakt + activeert de kaart, betaling vastgelegd
    $card = $this->postJson('/api/staff/cards', [
        'customer_id' => $this->customer->id,
        'card_product_id' => $this->product->id,
        'payment' => ['method' => 'pin'],
    ])->assertStatus(201);

    $cardId = $card->json('card.id');
    expect($card->json('card.status'))->toBe('active')
        ->and($card->json('card.cups_remaining'))->toBe(12);

    // Klant ziet de kaart live in de PWA
    asCustomer($this->customer);
    $this->getJson('/api/pwa/me')
        ->assertOk()
        ->assertJsonPath('cards.0.id', $cardId)
        ->assertJsonPath('cards.0.cups_remaining', 12);

    // C1 — klant toont redeem-QR
    $redeemNonce = $this->postJson('/api/pwa/tokens', ['purpose' => 'redeem', 'card_id' => $cardId])
        ->assertOk()->json('nonce');

    // C2 — balie scant: −1 kop
    asStaff($this->staff);
    $this->postJson('/api/staff/scan', ['nonce' => $redeemNonce])
        ->assertOk()
        ->assertJsonPath('result', 'redeemed')
        ->assertJsonPath('card.cups_remaining', 11);

    // Grootboek herleidbaar tot de staff-user (criterium 7)
    $event = Card::find($cardId)->events()->where('type', 'redeem')->latest('id')->first();
    expect($event->staff_user_id)->toBe($this->staff->id);
});

it('maakt een token na één scan ongeldig (single-use)', function () {
    $card = Card::factory()->active()->for($this->customer)->for($this->product, 'cardProduct')->create();

    asCustomer($this->customer);
    $nonce = $this->postJson('/api/pwa/tokens', ['purpose' => 'redeem', 'card_id' => $card->id])
        ->json('nonce');

    asStaff($this->staff);
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])->assertOk();
    // Tweede scan van dezelfde nonce faalt.
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])
        ->assertStatus(409)
        ->assertJsonPath('code', 'token_consumed');
});

it('weigert een verlopen token (criterium 2)', function () {
    $card = Card::factory()->active()->for($this->customer)->for($this->product, 'cardProduct')->create();
    $nonce = 'bekende-test-nonce';
    QrToken::factory()->expired()->create([
        'nonce_hash' => hash('sha256', $nonce),
        'subject_id' => $card->id,
    ]);

    asStaff($this->staff);
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])
        ->assertStatus(409)
        ->assertJsonPath('code', 'token_expired');
});

it('geeft geen kaart aan een klant zonder geverifieerd e-mailadres (criterium 1)', function () {
    $unverified = Customer::factory()->unverified()->create();

    asStaff($this->staff);
    $this->postJson('/api/staff/cards', [
        'customer_id' => $unverified->id,
        'card_product_id' => $this->product->id,
        'payment' => ['method' => 'cash'],
    ])->assertStatus(422)->assertJsonPath('code', 'email_not_verified');
});

it('toont na herstel op een ander toestel exact dezelfde kaarten (criterium 5)', function () {
    Card::factory()->active()->for($this->customer)->for($this->product, 'cardProduct')->create();

    // Twee verschillende device-tokens (twee toestellen) zien dezelfde kaart.
    $tokenA = $this->customer->createToken('toestel-a', ['customer'])->plainTextToken;
    $tokenB = $this->customer->createToken('toestel-b', ['customer'])->plainTextToken;

    $cardsA = $this->withToken($tokenA)->getJson('/api/pwa/me')->json('cards');
    $cardsB = $this->withToken($tokenB)->getJson('/api/pwa/me')->json('cards');

    expect($cardsB)->toEqual($cardsA)->and($cardsA)->toHaveCount(1);
});

it('meldt dat een pending kaart eerst geactiveerd moet worden bij een redeem-scan', function () {
    $card = Card::factory()->for($this->customer)->for($this->product, 'cardProduct')->create([
        'status' => CardStatus::Pending, 'cups_remaining' => 12,
    ]);
    $nonce = 'pending-nonce';
    QrToken::factory()->create([
        'nonce_hash' => hash('sha256', $nonce),
        'subject_id' => $card->id,
    ]);

    asStaff($this->staff);
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])
        ->assertStatus(409)
        ->assertJsonPath('result', 'needs_activation');
});
