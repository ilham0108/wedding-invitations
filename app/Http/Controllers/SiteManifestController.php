<?php

namespace App\Http\Controllers;

use App\Services\FaviconService;
use Illuminate\Http\JsonResponse;

class SiteManifestController extends Controller
{
    /**
     * Web app manifest with the site name and the uploaded icon.
     */
    public function __invoke(FaviconService $favicon): JsonResponse
    {
        return response()->json($favicon->manifest(), headers: ['Content-Type' => 'application/manifest+json'])
            ->setPublic()
            ->setMaxAge(3600);
    }
}
