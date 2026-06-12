<?php

namespace App\Services;

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Exceptions\RedemptionException;
use App\Models\Card;
use App\Models\Drink;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;

/**
 * Verzilvert koppen atomair. De kern is een conditionele UPDATE die alleen slaagt
 * zolang de kaart actief is én cups_remaining > 0 — daardoor kan het saldo door
 * gelijktijdige scans nooit onder 0 of boven cups_total komen, ook zonder row locks
 * (zoals op SQLite). Het grootboek (card_events) blijft leidend.
 */
class RedemptionService
{
    /**
     * Verzilver precies één kop van een kaart.
     *
     * @throws RedemptionException als de kaart niet (meer) verzilverbaar is.
     */
    public function redeemCup(Card $card, ?StaffUser $staff = null, ?Drink $drink = null): Card
    {
        return DB::transaction(function () use ($card, $staff, $drink) {
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

            // Snapshot van het geschonken drankje (type/maat/kostprijs) voor analytics.
            $card->events()->create([
                'type' => CardEventType::Redeem,
                'cups_delta' => -1,
                'staff_user_id' => $staff?->getKey(),
                'drink_id' => $drink?->getKey(),
                'coffee_type' => $drink?->type,
                'cup_size' => $drink?->size,
                'cost_cents' => $drink?->cost_cents,
            ]);

            if ($card->cups_remaining === 0) {
                $card->update(['status' => CardStatus::Depleted]);
            }

            return $card;
        });
    }
}
