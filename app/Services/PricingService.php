<?php

namespace App\Services;

use App\Models\CardProduct;

/**
 * Prijs/marge-berekeningen voor koffiekaarten.
 *
 * De drie knoppen staan los van elkaar (CLAUDE.md §1):
 *   - korting        = cadeau_koppen / koppen_op_kaart           (alleen koppen, niet de prijs)
 *   - kaartprijs     = betaalde_koppen × prijs_per_kop
 *   - marge_per_kaart = kaartprijs − (geleverde_koppen × kostprijs_per_kop)
 *
 * Alle bedragen in hele centen (integers), nooit floats.
 */
class PricingService
{
    /** Aantal cadeau-koppen: totaal minus betaald. */
    public function giftCups(int $cupsTotal, int $cupsPaid): int
    {
        return max(0, $cupsTotal - $cupsPaid);
    }

    /**
     * Korting als fractie (0..1), uitsluitend bepaald door de koppen-verhouding.
     * Onafhankelijk van de prijs per kop — bewijst criterium 6.
     */
    public function discountRate(int $cupsTotal, int $cupsPaid): float
    {
        if ($cupsTotal <= 0) {
            return 0.0;
        }

        return $this->giftCups($cupsTotal, $cupsPaid) / $cupsTotal;
    }

    /** Kaartprijs in centen: betaalde koppen × prijs per kop. */
    public function cardPriceCents(int $cupsPaid, int $pricePerCupCents): int
    {
        return $cupsPaid * $pricePerCupCents;
    }

    /**
     * Marge in centen wanneer alle koppen geleverd zijn:
     * kaartprijs − (geleverde_koppen × kostprijs_per_kop).
     */
    public function marginCents(int $cupsTotal, int $cupsPaid, int $pricePerCupCents, int $costPerCupCents): int
    {
        return $this->cardPriceCents($cupsPaid, $pricePerCupCents) - ($cupsTotal * $costPerCupCents);
    }

    /** Volledige opbouw voor een concreet product. */
    public function forProduct(CardProduct $product): array
    {
        return [
            'gift_cups' => $this->giftCups($product->cups_total, $product->cups_paid),
            'discount_rate' => $this->discountRate($product->cups_total, $product->cups_paid),
            'card_price_cents' => $this->cardPriceCents($product->cups_paid, $product->price_per_cup_cents),
            'margin_cents' => $this->marginCents(
                $product->cups_total,
                $product->cups_paid,
                $product->price_per_cup_cents,
                $product->cost_per_cup_cents,
            ),
        ];
    }
}
