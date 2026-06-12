<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Gegooid wanneer een kop niet verzilverd kan worden
 * (kaart niet actief, leeg, verlopen of voided).
 */
class RedemptionException extends RuntimeException
{
    public static function notRedeemable(): self
    {
        return new self('De kaart kan niet verzilverd worden (niet actief of geen koppen meer).');
    }
}
