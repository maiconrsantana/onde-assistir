<?php

namespace App\Exceptions;

use RuntimeException;

class BroadcastFinderException extends RuntimeException
{
    public static function missingConfiguration(string $key): self
    {
        return new self("Missing broadcast finder configuration: {$key}");
    }

    public static function requestFailed(string $provider, int $status, string $message = ''): self
    {
        $suffix = $message !== '' ? " {$message}" : '';

        return new self("{$provider} request failed with HTTP {$status}.{$suffix}");
    }

    public static function invalidPayload(string $provider, string $reason): self
    {
        return new self("{$provider} returned an invalid payload: {$reason}");
    }
}
