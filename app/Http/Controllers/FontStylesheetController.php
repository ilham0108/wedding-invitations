<?php

namespace App\Http\Controllers;

use App\Services\FontRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FontStylesheetController extends Controller
{
    /** Enough for the admin font page previews; unknown names are dropped anyway. */
    private const MAX_FAMILIES = 60;

    /**
     * CSS for ?families=Name|Name (unknown names are ignored). The URL carries the registry version (?v=),
     * so the response can be cached for good: a font change produces a new URL.
     */
    public function __invoke(Request $request, FontRegistry $fonts): Response
    {
        $names = array_slice(explode('|', (string) $request->query('families')), 0, self::MAX_FAMILIES);

        return response($fonts->stylesheet($names), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
