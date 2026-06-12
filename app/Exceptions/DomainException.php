<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Basis voor voorspelbare domeinfouten die als nette JSON terugkomen
 * ({ "code": "...", "message": "..." }) met een passende HTTP-status.
 */
abstract class DomainException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function httpStatus(): int
    {
        return 422;
    }
}
