<?php

namespace App\Exceptions;

/**
 * Gegooid bij een ongeldige, verlopen of reeds gebruikte QR-token.
 */
class TokenException extends DomainException
{
    public function __construct(string $message, private readonly string $errCode = 'token_invalid')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errCode;
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public static function invalid(): self
    {
        return new self('Onbekende of ongeldige code.', 'token_invalid');
    }

    public static function expired(): self
    {
        return new self('De code is verlopen. Vraag een nieuwe QR.', 'token_expired');
    }

    public static function alreadyUsed(): self
    {
        return new self('Deze code is al gebruikt.', 'token_consumed');
    }
}
