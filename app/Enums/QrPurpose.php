<?php

namespace App\Enums;

enum QrPurpose: string
{
    /** Identificeert een klant aan de balie (start nieuwe-kaart-flow). */
    case Identify = 'identify';

    /** Verzilvert één kop van een specifieke kaart. */
    case Redeem = 'redeem';
}
