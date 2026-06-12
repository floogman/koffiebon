<?php

namespace App\Enums;

/**
 * Types in het onveranderlijke grootboek (card_events).
 *
 * cups_remaining = cups_total + som(cups_delta van redeem/void/adjust).
 * issue/activate zijn markeringen met cups_delta = 0.
 */
enum CardEventType: string
{
    case Issue = 'issue';
    case Activate = 'activate';
    case Redeem = 'redeem';
    case Void = 'void';
    case Adjust = 'adjust';
}
