<?php

namespace App\Events;

use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wordt uitgezonden zodra een kaart aan de balie verandert (verzilverd, geactiveerd
 * of net uitgegeven). De klant-PWA luistert op haar privé-kanaal en werkt het saldo
 * direct bij — geen polling nodig.
 *
 * `action`: 'redeemed' | 'activated' | 'issued'.
 */
class CardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Card $card,
        public string $action,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('Customer.'.$this->card->customer_id)];
    }

    public function broadcastAs(): string
    {
        return 'card.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'card' => (new CardResource($this->card->loadMissing('cardProduct')))->resolve(),
        ];
    }
}
