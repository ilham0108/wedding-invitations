<?php

namespace App\Support;

/**
 * Builds a CSS url() value for inline styles. Characters that could end the url()
 * token or the style attribute are percent-encoded, so a (validated) URL can never
 * inject CSS. Callers still pass only URLs that went through InvitationRenderer::safeUrl().
 */
final class CssUrl
{
    public static function wrap(?string $url): string
    {
        if ($url === null || $url === '') {
            return 'none';
        }

        $encoded = str_replace(
            ['%', '"', "'", '(', ')', '\\', ' ', "\n", "\r", "\t", '<', '>'],
            ['%25', '%22', '%27', '%28', '%29', '%5C', '%20', '', '', '', '%3C', '%3E'],
            $url,
        );

        // Keep "%25" from double-encoding existing escapes such as %20.
        $encoded = preg_replace('/%25([0-9A-Fa-f]{2})/', '%$1', $encoded);

        return 'url("'.$encoded.'")';
    }
}
