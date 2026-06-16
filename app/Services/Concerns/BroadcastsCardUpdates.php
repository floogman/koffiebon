<?php

namespace App\Services\Concerns;

use App\Events\CardUpdated;
use App\Models\Card;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Zendt een live kaart-update uit naar de klant-PWA. Broadcasten is best-effort:
 * als de Reverb-server onbereikbaar is mag dat de balie-flow (verzilveren/activeren)
 * nooit laten falen — we loggen dan alleen een waarschuwing.
 */
trait BroadcastsCardUpdates
{
    protected function broadcastCardUpdated(Card $card, string $action): void
    {
        try {
            CardUpdated::dispatch($card, $action);
        } catch (Throwable $e) {
            Log::warning('Kaart-update broadcast mislukt', [
                'card_id' => $card->getKey(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
