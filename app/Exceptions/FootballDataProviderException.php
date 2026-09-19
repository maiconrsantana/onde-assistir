<?php

namespace App\Exceptions;

use RuntimeException;

class FootballDataProviderException extends RuntimeException
{
    public static function missingConfiguration(string $key): self
    {
        return new self("Missing football data provider configuration: {$key}");
    }

    public static function requestFailed(int $status, string $message = ''): self
    {
        $suffix = $message !== '' ? " {$message}" : '';

        return new self("Football data provider request failed with HTTP {$status}.{$suffix}");
    }

    public static function invalidPayload(string $reason): self
    {
        return new self("Football data provider returned an invalid payload: {$reason}");
    }
}
