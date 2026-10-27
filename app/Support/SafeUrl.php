<?php

namespace App\Support;

/**
 * Only http(s) URLs and root-relative paths may reach src/href attributes.
 */
final class SafeUrl
{
    public static function filter(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}
