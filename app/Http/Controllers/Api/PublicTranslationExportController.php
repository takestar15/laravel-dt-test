<?php

namespace App\Http\Controllers\Api;

use App\Actions\Translations\InvalidateTranslationCacheAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExportTranslationsRequest;
use App\Support\Translations\TranslationExport;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class PublicTranslationExportController extends Controller
{
    /**
     * Export public translations with CDN-friendly caching.
     *
     * Return a public, cacheable translation payload for frontend clients,
     * including ETag and shared-cache headers for CDN delivery.
     *
     * @unauthenticated
     */
    public function __invoke(
        ExportTranslationsRequest $request,
        TranslationExport $translationExport,
        InvalidateTranslationCacheAction $invalidateTranslationCache,
    ): Response {
        $filters = [
            'locale' => $request->string('locale')->toString() ?: null,
            'tags' => $this->normalizeTags($request->collect('tags')->all()),
        ];

        $version = $invalidateTranslationCache->currentVersion();
        $json = $translationExport->toJson($filters);
        $etag = sha1(implode('|', [
            'translations',
            'public-export',
            $version,
            $filters['locale'] ?? 'all',
            implode(',', $filters['tags']),
        ]));

        $response = response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60, s-maxage=300, stale-while-revalidate=30',
            'Surrogate-Key' => "translations translations-v{$version}",
            'X-Translation-Version' => (string) $version,
        ])->setEtag($etag);

        $response->isNotModified($request);

        return $response;
    }

    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function normalizeTags(array $tags): array
    {
        return Collection::make($tags)
            ->filter(fn (mixed $tag): bool => is_string($tag))
            ->map(fn (string $tag): string => mb_strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
