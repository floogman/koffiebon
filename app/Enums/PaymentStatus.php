<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Fysieke betaling vastgelegd (fase 1: pin/contant aan de balie). */
    case Recorded = 'recorded';

    /** Online betaald (fase 2: Mollie). */
    case Paid = 'paid';

    case Failed = 'failed';
    case Refunded = 'refunded';
}
