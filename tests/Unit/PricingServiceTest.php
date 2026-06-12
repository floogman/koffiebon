<?php

use App\Services\PricingService;

beforeEach(function () {
    $this->pricing = new PricingService();
});

it('berekent de kaartprijs als betaalde koppen × prijs per kop', function () {
    // 10 betaalde koppen × € 3,00 = € 30,00
    expect($this->pricing->cardPriceCents(10, 300))->toBe(3000);
});

it('berekent cadeau-koppen als totaal minus betaald', function () {
    expect($this->pricing->giftCups(12, 10))->toBe(2);
});

it('berekent de marge als kaartprijs minus geleverde koppen × kostprijs', function () {
    // 10 × 300 = 3000 kaartprijs; 12 geleverde koppen × 60 kostprijs = 720; marge = 2280
    expect($this->pricing->marginCents(12, 10, 300, 60))->toBe(2280);
});

/**
 * Acceptatiecriterium 6: de korting volgt uit de koppen-verhouding en blijft
 * gelijk als de prijs per kop verandert.
 */
it('houdt de korting constant als de prijs per kop verandert', function () {
    $rateGoedkoop = $this->pricing->discountRate(12, 10);
    $rateDuur = $this->pricing->discountRate(12, 10);

    // Zelfde koppen, andere prijzen → de prijs speelt geen rol in de korting.
    expect($this->pricing->cardPriceCents(10, 250))->toBe(2500)
        ->and($this->pricing->cardPriceCents(10, 400))->toBe(4000)
        ->and($rateGoedkoop)->toBe($rateDuur)
        ->and(round($rateGoedkoop, 4))->toBe(round(2 / 12, 4));
});

it('geeft 0 korting bij een kaart zonder cadeau-koppen', function () {
    expect($this->pricing->discountRate(10, 10))->toBe(0.0)
        ->and($this->pricing->giftCups(10, 10))->toBe(0);
});
