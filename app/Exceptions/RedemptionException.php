<?php

namespace App\Exceptions;

/**
 * Gegooid wanneer een kop niet verzilverd kan worden
 * (kaart niet actief, leeg, verlopen of voided).
 */
class RedemptionException extends DomainException
{
    public function errorCode(): string
    {
        return 'card_not_redeemable';
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public static function notRedeemable(): self
    {
        return new self('De kaart kan niet verzilverd worden (niet actief of geen koppen meer).');
    }
}
