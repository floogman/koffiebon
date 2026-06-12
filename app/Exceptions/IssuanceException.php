<?php

namespace App\Exceptions;

/**
 * Gegooid wanneer een kaart niet uitgegeven/geactiveerd kan worden
 * (bv. e-mail niet geverifieerd, of kaart niet pending).
 */
class IssuanceException extends DomainException
{
    public function __construct(string $message, private readonly string $errCode = 'issuance_failed')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errCode;
    }

    public function httpStatus(): int
    {
        return 422;
    }

    public static function emailNotVerified(): self
    {
        return new self('De klant heeft nog geen geverifieerd e-mailadres.', 'email_not_verified');
    }

    public static function notPending(): self
    {
        return new self('Deze kaart is niet (meer) in afwachting van activatie.', 'card_not_pending');
    }
}
