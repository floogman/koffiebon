<?php

namespace App\Services;

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Exceptions\RedemptionException;
use App\Models\Card;
use App\Models\Drink;
use App\Models\StaffUser;
use App\Services\Concerns\BroadcastsCardUpdates;
use Illuminate\Support\Facades\DB;

/**
 * Verzilvert koppen atomair. De kern is een conditionele UPDATE die alleen slaagt
 * zolang de kaart actief is én cups_remaining > 0 — daardoor kan het saldo door
 * gelijktijdige scans nooit onder 0 of boven cups_total komen, ook zonder row locks
 * (zoals op SQLite). Het grootboek (card_events) blijft leidend.
 */
class RedemptionService
{
    use BroadcastsCardUpdates;

    /**
     * Verzilver precies één kop van een kaart.
     *
     * @throws RedemptionException als de kaart niet (meer) verzilverbaar is.
     */
    public function redeemCup(Card $card, ?StaffUser $staff = null, ?Drink $drink = null): Card
    {
        $card = DB::transaction(function () use ($card, $staff, $drink) {
            // Atomaire, conditionele decrement. Slaagt voor hooguit één gelijktijdige scan.
            $affected = Card::query()
                ->whereKey($card->getKey())
                ->where('status', CardStatus::Active->value)
                ->where('cups_remaining', '>', 0)
                ->update(['cups_remaining' => DB::raw('cups_remaining - 1')]);

            if ($affected === 0) {
                throw RedemptionException::notRedeemable();
            }

            $card->refresh();

            // Het geschonken drankje staat vast op de kaart (type/maat als tekst). De
            // bijpassende drink-rij levert alleen de kostprijs/id voor analytics — die kan
            // ontbreken als de drankenkaart sinds de aankoop is gewijzigd.
            $card->events()->create([
                'type' => CardEventType::Redeem,
                'cups_delta' => -1,
                'staff_user_id' => $staff?->getKey(),
                'drink_id' => $drink?->getKey(),
                'coffee_type' => $card->preferred_coffee_type,
                'cup_size' => $card->preferred_cup_size,
                'cost_cents' => $drink?->cost_cents,
            ]);

            if ($card->cups_remaining === 0) {
                $card->update(['status' => CardStatus::Depleted]);
            }

            return $card;
        });

        // Na commit: stuur het nieuwe saldo live naar de PWA.
        $this->broadcastCardUpdated($card, 'redeemed');

        return $card;
    }
}
