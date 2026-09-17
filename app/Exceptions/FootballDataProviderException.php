<?php

namespace App\Exceptions;

use RuntimeException;

class FootballDataProviderException extends RuntimeException
{
    public static function missingConfiguration(string $key): self
    {
        return new self("Missing API-Football configuration: {$key}");
    }

    public static function requestFailed(int $status, string $message = ''): self
    {
        $suffix = $message !== '' ? " {$message}" : '';

        return new self("API-Football request failed with HTTP {$status}.{$suffix}");
    }

    public static function invalidPayload(string $reason): self
    {
        return new self("API-Football returned an invalid payload: {$reason}");
    }
}
