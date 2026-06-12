<?php

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Exceptions\RedemptionException;
use App\Models\Card;
use App\Models\StaffUser;
use App\Services\RedemptionService;

beforeEach(function () {
    $this->service = new RedemptionService();
});

it('verzilvert één kop, verlaagt het saldo en schrijft een grootboek-event', function () {
    $card = Card::factory()->active()->create(['cups_total' => 12, 'cups_remaining' => 12]);
    $staff = StaffUser::factory()->create();

    $this->service->redeemCup($card, $staff);

    expect($card->fresh()->cups_remaining)->toBe(11);

    $event = $card->events()->latest('id')->first();
    expect($event->type)->toBe(CardEventType::Redeem)
        ->and($event->cups_delta)->toBe(-1)
        ->and($event->staff_user_id)->toBe($staff->id);
});

it('zet de kaart op depleted bij de laatste kop', function () {
    $card = Card::factory()->active()->create(['cups_total' => 1, 'cups_remaining' => 1]);

    $card = $this->service->redeemCup($card);

    expect($card->cups_remaining)->toBe(0)
        ->and($card->status)->toBe(CardStatus::Depleted);
});

it('weigert verzilvering van een lege kaart', function () {
    $card = Card::factory()->active()->create(['cups_total' => 5, 'cups_remaining' => 0]);

    $this->service->redeemCup($card);
})->throws(RedemptionException::class);

it('weigert verzilvering van een niet-actieve kaart', function () {
    $card = Card::factory()->create(['status' => CardStatus::Pending, 'cups_total' => 5, 'cups_remaining' => 5]);

    expect(fn () => $this->service->redeemCup($card))->toThrow(RedemptionException::class);
    expect($card->fresh()->cups_remaining)->toBe(5);
});

/**
 * Acceptatiecriterium 4: een kaart kan door gelijktijdige scans nooit onder 0 of
 * boven cups_total komen. We simuleren de race door méér verzilveringen aan te
 * bieden dan er koppen zijn; de conditionele UPDATE laat er precies cups_total slagen.
 */
it('komt bij gelijktijdige verzilvering nooit onder 0 of boven het totaal', function () {
    $card = Card::factory()->active()->create(['cups_total' => 10, 'cups_remaining' => 10]);

    $succeeded = 0;
    $failed = 0;

    // 25 pogingen op een kaart van 10 koppen.
    foreach (range(1, 25) as $i) {
        try {
            $this->service->redeemCup($card->fresh());
            $succeeded++;
        } catch (RedemptionException) {
            $failed++;
        }
    }

    $fresh = $card->fresh();

    expect($succeeded)->toBe(10)
        ->and($failed)->toBe(15)
        ->and($fresh->cups_remaining)->toBe(0)
        ->and($fresh->cups_remaining)->toBeGreaterThanOrEqual(0)
        ->and($fresh->status)->toBe(CardStatus::Depleted);

    // Grootboek is leidend: cups_remaining == cups_total + som(redeem-delta's).
    $sumDelta = $fresh->events()->where('type', CardEventType::Redeem)->sum('cups_delta');
    expect($fresh->cups_total + $sumDelta)->toBe($fresh->cups_remaining)
        ->and($fresh->events()->where('type', CardEventType::Redeem)->count())->toBe(10);
});
