<?php

namespace App\Support;

final class ExternalUrl
{
    public static function normalize(?string $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        return $url;
    }

    public static function isValid(?string $url): bool
    {
        return $url === null || $url === '' || self::normalize($url) !== null;
    }
}
