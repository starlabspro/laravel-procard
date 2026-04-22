<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Exceptions;

use Exception;

class ProcardException extends Exception
{
    public static function configMissing(string $key): self
    {
        return new self("Procard configuration key '{$key}' is missing.");
    }

    public static function callbackValidationFailed(string $reason): self
    {
        return new self("Procard callback validation failed: {$reason}");
    }

    public static function requestFailed(string $reason): self
    {
        return new self("Procard request failed: {$reason}");
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Procard invalid response: {$reason}");
    }
}
