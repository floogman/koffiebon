<?php

namespace App\Enums;

enum CardStatus: string
{
    /** Uitgegeven maar nog niet betaald/geactiveerd aan de balie. */
    case Pending = 'pending';

    /** Betaald en geactiveerd; koppen kunnen verzilverd worden. */
    case Active = 'active';

    /** Alle koppen verzilverd (cups_remaining = 0). */
    case Depleted = 'depleted';

    /** Voorbij expires_at. */
    case Expired = 'expired';

    /** Geannuleerd/ongeldig gemaakt. */
    case Void = 'void';
}
