<?php

use App\Events\CardUpdated;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\QrToken;
use App\Models\StaffUser;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->merchant = Merchant::factory()->create();
    $this->location = Location::factory()->for($this->merchant)->create();
    $this->staff = StaffUser::factory()->for($this->merchant)->for($this->location)->create();
    $this->product = CardProduct::factory()->for($this->merchant)->create([
        'cups_total' => 12, 'cups_paid' => 10, 'price_per_cup_cents' => 300,
    ]);
    $this->customer = Customer::factory()->create();
});

it('zendt CardUpdated(redeemed) uit op het klant-kanaal na een verzilvering', function () {
    Event::fake([CardUpdated::class]);

    $card = Card::factory()->active()->for($this->customer)->for($this->product, 'cardProduct')->create();
    $nonce = 'redeem-broadcast-nonce';
    QrToken::factory()->create([
        'nonce_hash' => hash('sha256', $nonce),
        'subject_id' => $card->id,
    ]);

    Sanctum::actingAs($this->staff, ['staff']);
    $this->postJson('/api/staff/scan', ['nonce' => $nonce])->assertOk();

    Event::assertDispatched(CardUpdated::class, function (CardUpdated $e) use ($card) {
        return $e->action === 'redeemed'
            && $e->card->id === $card->id
            && collect($e->broadcastOn())->contains(
                fn (PrivateChannel $c) => $c->name === 'private-Customer.'.$this->customer->id
            );
    });
});

it('zendt CardUpdated(issued) uit wanneer de balie een nieuwe kaart aanmaakt', function () {
    Event::fake([CardUpdated::class]);

    Sanctum::actingAs($this->staff, ['staff']);
    $this->postJson('/api/staff/cards', [
        'customer_id' => $this->customer->id,
        'card_product_id' => $this->product->id,
        'payment' => ['method' => 'pin'],
        'preferred_coffee_type' => 'cappuccino',
        'preferred_cup_size' => 'medium',
    ])->assertStatus(201);

    Event::assertDispatched(CardUpdated::class, fn (CardUpdated $e) => $e->action === 'issued'
        && $e->card->customer_id === $this->customer->id);
});
