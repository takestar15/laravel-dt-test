<?php

namespace App\Http\Controllers\Api;

use App\Actions\Translations\InvalidateTranslationCacheAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicTranslationVersionController extends Controller
{
    /**
     * Return the current public translation version.
     *
     * This lightweight endpoint helps frontend applications build versioned
     * translation export requests that work well with CDN caching.
     *
     * @unauthenticated
     */
    public function __invoke(
        InvalidateTranslationCacheAction $invalidateTranslationCache,
    ): JsonResponse {
        return response()->json([
            'version' => $invalidateTranslationCache->currentVersion(),
        ], headers: [
            'Cache-Control' => 'public, max-age=30, s-maxage=30, stale-while-revalidate=30',
        ]);
    }
}
