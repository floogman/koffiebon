<?php

namespace App\Exceptions;

/**
 * Voorspelbare fouten in de cross-device login-flow. Komt als { code, message } terug.
 * `login_pending` is geen echte fout: de PWA polt door tot de e-maillink bevestigd is.
 */
class LoginException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $errCode,
        private readonly int $status = 409,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errCode;
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public static function pending(): self
    {
        return new self('Nog niet bevestigd.', 'login_pending', 409);
    }

    public static function invalid(): self
    {
        return new self('Onbekende of ongeldige login.', 'login_invalid', 404);
    }

    public static function expired(): self
    {
        return new self('De login is verlopen. Vraag een nieuwe link aan.', 'login_expired', 410);
    }

    public static function consumed(): self
    {
        return new self('Deze login is al gebruikt.', 'login_consumed', 409);
    }
}
