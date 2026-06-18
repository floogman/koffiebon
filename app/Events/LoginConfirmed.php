<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Uitgezonden zodra een login-sessie via de e-maillink is bevestigd. Gaat over een
 * PUBLIEK kanaal `login.{secretHash}` — de naam is een hash van het 256-bit geheim dat
 * alleen de initiërende PWA kent, dus de facto privé.
 *
 * De payload bevat BEWUST geen klant-id en geen token: de push is enkel een seintje.
 * De PWA wisselt daarna zélf het geheim in voor een device-token (POST /api/auth/claim).
 * Zo kan niemand met enkel een id of hash een sessie kapen.
 *
 * ShouldBroadcastNow: direct uitzenden vanuit het confirm-request, zonder queue-vertraging.
 */
class LoginConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $secretHash) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('login.'.$this->secretHash)];
    }

    public function broadcastAs(): string
    {
        return 'login.confirmed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['confirmed' => true];
    }
}
