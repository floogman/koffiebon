<?php

use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Drink;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\StaffUser;
use App\Services\RedemptionService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->merchant = Merchant::factory()->create();
    $this->centrum = Location::factory()->for($this->merchant)->create(['name' => 'Centrum']);
    $this->station = Location::factory()->for($this->merchant)->create(['name' => 'Station']);
    $this->admin = StaffUser::factory()->for($this->merchant)->admin()->create();
    $this->balie = StaffUser::factory()->for($this->merchant)->for($this->centrum)->create();
    $this->product = CardProduct::factory()->for($this->merchant)->create([
        'cups_total' => 12, 'cups_paid' => 10, 'price_per_cup_cents' => 300,
    ]);
    $this->cappu = Drink::factory()->for($this->merchant)->create([
        'type' => CoffeeType::Cappuccino, 'size' => CupSize::Large, 'cost_cents' => 68,
    ]);
});

function activeCardAt($ctx, $location, CoffeeType $type = CoffeeType::Cappuccino, CupSize $size = CupSize::Large)
{
    // Het geschonken drankje volgt nu uit het vaste voorkeursdrankje van de kaart.
    return Card::factory()->active()
        ->for(Customer::factory())
        ->for($ctx->product, 'cardProduct')
        ->for($location)
        ->create([
            'cups_total' => 12, 'cups_remaining' => 12,
            'preferred_coffee_type' => $type,
            'preferred_cup_size' => $size,
        ]);
}

it('weigert het dashboard voor niet-admin staff', function () {
    Sanctum::actingAs($this->balie, ['staff']);
    $this->getJson('/api/staff/dashboard')->assertForbidden();
});

it('toont echte cijfers uit het grootboek voor een admin', function () {
    $svc = app(RedemptionService::class);

    // Centrum: 2 kaarten, 3 verzilveringen (met cappuccino-drankje).
    $c1 = activeCardAt($this, $this->centrum);
    activeCardAt($this, $this->centrum);
    $svc->redeemCup($c1, $this->balie, $this->cappu);
    $svc->redeemCup($c1->fresh(), $this->balie, $this->cappu);
    $svc->redeemCup($c1->fresh(), $this->balie, $this->cappu);

    // Station: 1 kaart met een ander vast drankje, 1 verzilvering zonder gematchte drink-rij.
    $s1 = activeCardAt($this, $this->station, CoffeeType::Regular, CupSize::Small);
    $svc->redeemCup($s1, $this->balie);

    Sanctum::actingAs($this->admin, ['staff']);
    $res = $this->getJson('/api/staff/dashboard')->assertOk();

    $res->assertJsonPath('summary.cards_sold', 3)
        ->assertJsonPath('summary.cups_redeemed', 4)
        ->assertJsonPath('summary.cups_outstanding', 12 * 3 - 4);

    // Drankkosten = 3 × 68 (cappuccino); station-redeem had geen drankje.
    expect($res->json('summary.drink_cost_cents'))->toBe(3 * 68);

    // Cappuccino-large telt 3 keer.
    $byType = collect($res->json('by_drink.by_type'))->firstWhere('type', 'cappuccino');
    expect($byType['sizes']['large'])->toBe(3)->and($byType['total'])->toBe(3);
});

it('filtert het dashboard op vestiging', function () {
    $svc = app(RedemptionService::class);
    $c = activeCardAt($this, $this->centrum);
    $svc->redeemCup($c, $this->balie, $this->cappu);
    $svc->redeemCup($c->fresh(), $this->balie, $this->cappu);
    $s = activeCardAt($this, $this->station);
    $svc->redeemCup($s, $this->balie);

    Sanctum::actingAs($this->admin, ['staff']);

    $centrum = $this->getJson("/api/staff/dashboard?location_id={$this->centrum->id}")->assertOk();
    expect($centrum->json('summary.cups_redeemed'))->toBe(2);

    $station = $this->getJson("/api/staff/dashboard?location_id={$this->station->id}")->assertOk();
    expect($station->json('summary.cups_redeemed'))->toBe(1);

    // by_location toont altijd alle vestigingen.
    expect($centrum->json('by_location'))->toHaveCount(2);
});

it('legt het vaste drankje van de kaart vast bij een scan-verzilvering', function () {
    // Kaart met vast drankje cappuccino-large; de balie kiest niets meer.
    $card = activeCardAt($this, $this->centrum, CoffeeType::Cappuccino, CupSize::Large);

    Sanctum::actingAs($card->customer, ['customer']);
    $nonce = $this->postJson('/api/pwa/tokens', ['purpose' => 'redeem', 'card_id' => $card->id])->json('nonce');

    Sanctum::actingAs($this->balie, ['staff']);
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])
        ->assertOk()
        ->assertJsonPath('result', 'redeemed')
        ->assertJsonPath('drink.type', 'cappuccino')
        ->assertJsonPath('drink.size', 'large');

    $event = $card->events()->where('type', 'redeem')->latest('id')->first();
    expect($event->coffee_type)->toBe(CoffeeType::Cappuccino)
        ->and($event->cup_size)->toBe(CupSize::Large)
        ->and($event->cost_cents)->toBe(68)
        ->and($event->drink_id)->toBe($this->cappu->id);
});
