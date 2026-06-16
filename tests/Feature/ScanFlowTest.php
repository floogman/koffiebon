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
    // B1 — klant kiest een vast drankje en toont de identify-QR
    asCustomer($this->customer);
    $identify = $this->postJson('/api/pwa/tokens', [
        'purpose' => 'identify',
        'preferred_coffee_type' => 'cappuccino',
        'preferred_cup_size' => 'medium',
    ])->assertOk();
    $identifyNonce = $identify->json('nonce');
    $identify->assertJsonPath('preferred_drink', 'Cappuccino · Medium');

    // B2 — balie scant identify-token en ziet de gekozen drank
    asStaff($this->staff);
    $scan = $this->postJson('/api/staff/scan', ['nonce' => $identifyNonce])->assertOk();
    $scan->assertJsonPath('type', 'identify')
        ->assertJsonPath('customer.id', $this->customer->id)
        ->assertJsonPath('preferred_drink.label', 'Cappuccino · Medium');

    // B3 — balie maakt + activeert de kaart (met de gekozen drank), betaling vastgelegd
    $card = $this->postJson('/api/staff/cards', [
        'customer_id' => $this->customer->id,
        'card_product_id' => $this->product->id,
        'payment' => ['method' => 'pin'],
        'preferred_coffee_type' => $scan->json('preferred_drink.type'),
        'preferred_cup_size' => $scan->json('preferred_drink.size'),
    ])->assertStatus(201);

    $cardId = $card->json('card.id');
    expect($card->json('card.status'))->toBe('active')
        ->and($card->json('card.cups_remaining'))->toBe(12)
        ->and($card->json('card.preferred_drink_label'))->toBe('Cappuccino · Medium');

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

it('eist een gekozen drankje bij het kopen van een kaart (identify-QR)', function () {
    asCustomer($this->customer);
    $this->postJson('/api/pwa/tokens', ['purpose' => 'identify'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['preferred_coffee_type', 'preferred_cup_size']);
});

it('verzilvert ook via de 6-cijferige baliecode in plaats van de QR', function () {
    $card = Card::factory()->active()->for($this->customer)->for($this->product, 'cardProduct')->create();

    asCustomer($this->customer);
    $token = $this->postJson('/api/pwa/tokens', ['purpose' => 'redeem', 'card_id' => $card->id])
        ->assertOk();
    $code = $token->json('code');
    expect($code)->toMatch('/^[1-9]\d{5}$/'); // 6 cijfers, geen voorloopnul

    // Balie typt de code in het nonce-veld; dezelfde token wordt geconsumeerd.
    asStaff($this->staff);
    $this->postJson('/api/staff/scan', ['nonce' => $code])
        ->assertOk()
        ->assertJsonPath('result', 'redeemed')
        ->assertJsonPath('card.cups_remaining', 11);

    // Code is single-use: tweede keer faalt.
    $this->postJson('/api/staff/scan', ['nonce' => $code])
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
        'preferred_coffee_type' => 'espresso',
        'preferred_cup_size' => 'small',
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
