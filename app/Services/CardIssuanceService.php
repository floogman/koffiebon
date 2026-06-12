<?php

namespace App\Services;

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\IssuanceException;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;

/**
 * Geeft kaarten uit en activeert ze. De kaartprijs wordt server-side afgeleid
 * uit het product (cups_paid × price_per_cup_cents) — de balie kiest alleen de
 * betaalmethode. Een geverifieerd e-mailadres is verplicht (criterium 1).
 */
class CardIssuanceService
{
    /**
     * issue + activate + payment in één transactie (de balie-flow).
     *
     * @throws IssuanceException als de klant geen geverifieerd e-mailadres heeft.
     */
    public function issueAndActivate(
        Customer $customer,
        CardProduct $product,
        PaymentMethod $method,
        ?StaffUser $staff = null,
        ?Location $location = null,
    ): Card {
        if (! $customer->hasVerifiedEmail()) {
            throw IssuanceException::emailNotVerified();
        }

        return DB::transaction(function () use ($customer, $product, $method, $staff, $location) {
            $price = $product->cups_paid * $product->price_per_cup_cents;

            $card = Card::create([
                'customer_id' => $customer->getKey(),
                'card_product_id' => $product->getKey(),
                'location_id' => $location?->getKey(),
                'status' => CardStatus::Active,
                'cups_total' => $product->cups_total,
                'cups_remaining' => $product->cups_total,
                'price_paid_cents' => $price,
                'activated_at' => now(),
                'expires_at' => now()->addDays($product->validity_days),
            ]);

            $this->writeEvent($card, CardEventType::Issue, $staff);
            $this->writeEvent($card, CardEventType::Activate, $staff);
            $this->recordPayment($card, $method, $price);

            return $card;
        });
    }

    /**
     * Activeer een bestaande pending-kaart (na fysieke betaling aan de balie).
     *
     * @throws IssuanceException als de kaart niet pending is.
     */
    public function activate(Card $card, PaymentMethod $method, ?StaffUser $staff = null): Card
    {
        if ($card->status !== CardStatus::Pending) {
            throw IssuanceException::notPending();
        }

        return DB::transaction(function () use ($card, $method, $staff) {
            $price = $card->price_paid_cents ?? ($card->cardProduct->cups_paid * $card->cardProduct->price_per_cup_cents);

            $card->update([
                'status' => CardStatus::Active,
                'cups_remaining' => $card->cups_total,
                'price_paid_cents' => $price,
                'activated_at' => now(),
                'expires_at' => now()->addDays($card->cardProduct->validity_days),
            ]);

            $this->writeEvent($card, CardEventType::Activate, $staff);
            $this->recordPayment($card, $method, $price);

            return $card->refresh();
        });
    }

    private function writeEvent(Card $card, CardEventType $type, ?StaffUser $staff): void
    {
        $card->events()->create([
            'type' => $type,
            'cups_delta' => 0,
            'staff_user_id' => $staff?->getKey(),
        ]);
    }

    private function recordPayment(Card $card, PaymentMethod $method, int $amountCents): void
    {
        Payment::create([
            'card_id' => $card->getKey(),
            'method' => $method,
            'amount_cents' => $amountCents,
            'status' => PaymentStatus::Recorded,
        ]);
    }
}
