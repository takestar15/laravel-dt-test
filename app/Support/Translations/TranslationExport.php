<?php

namespace App\Support\Translations;

use App\Actions\Translations\InvalidateTranslationCacheAction;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;
use stdClass;

class TranslationExport
{
    public function __construct(
        private readonly InvalidateTranslationCacheAction $invalidateTranslationCache,
    ) {}

    /**
     * @param  array{locale?: ?string, tags?: array<int, string>}  $filters
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function handle(array $filters): array
    {
        return json_decode($this->toJson($filters), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array{locale?: ?string, tags?: array<int, string>}  $filters
     */
    public function toJson(array $filters): string
    {
        $normalizedTags = $this->normalizeTags($filters['tags'] ?? []);

        $cacheKey = implode(':', [
            'translations',
            'export',
            'v'.$this->invalidateTranslationCache->currentVersion(),
            $filters['locale'] ?? 'all',
            md5(json_encode($normalizedTags)),
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($filters, $normalizedTags): string {
            $stream = fopen('php://temp/maxmemory:5242880', 'w+');

            if ($stream === false) {
                return '{}';
            }

            $this->writeJsonPayload(
                $stream,
                $this->buildQuery($filters['locale'] ?? null, $normalizedTags)->toBase()->cursor(),
                $filters['locale'] ?? null
            );

            rewind($stream);

            return stream_get_contents($stream) ?: '{}';
        });
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    private function normalizeTags(array $tags): array
    {
        return Collection::make($tags)
            ->map(fn (string $tag): string => mb_strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $normalizedTags
     */
    private function buildQuery(?string $locale, array $normalizedTags): Builder
    {
        $query = Translation::query()
            ->select(['locale', 'key', 'value'])
            ->when($locale, fn (Builder $builder, string $requestedLocale) => $builder->forLocale($requestedLocale))
            ->orderBy('locale')
            ->orderBy('key');

        if ($normalizedTags !== []) {
            $query->whereHas('tags', function (Builder $tagQuery) use ($normalizedTags): void {
                $tagQuery->whereIn('name', $normalizedTags);
            }, '=', count($normalizedTags));
        }

        return $query;
    }

    /**
     * @param  iterable<stdClass>  $translations
     * @param  resource  $stream
     *
     * @throws JsonException
     */
    private function writeJsonPayload($stream, iterable $translations, ?string $locale): void
    {
        fwrite($stream, '{');

        $openParents = [];
        $firstEntryByDepth = [true];

        foreach ($translations as $translation) {
            $segments = explode('.', $translation->key);

            if ($locale === null) {
                array_unshift($segments, $translation->locale);
            }

            $leaf = array_pop($segments);

            if ($leaf === null) {
                continue;
            }

            $sharedDepth = $this->sharedDepth($openParents, $segments);

            while (count($openParents) > $sharedDepth) {
                fwrite($stream, '}');
                array_pop($openParents);
                array_pop($firstEntryByDepth);
            }

            for ($depth = $sharedDepth; $depth < count($segments); $depth++) {
                $this->writeSeparator($stream, $firstEntryByDepth, count($firstEntryByDepth) - 1);
                fwrite($stream, $this->encode($segments[$depth]).':{');
                $openParents[] = $segments[$depth];
                $firstEntryByDepth[] = true;
            }

            $this->writeSeparator($stream, $firstEntryByDepth, count($firstEntryByDepth) - 1);
            fwrite($stream, $this->encode($leaf).':'.$this->encode($translation->value));
        }

        while ($openParents !== []) {
            fwrite($stream, '}');
            array_pop($openParents);
        }

        fwrite($stream, '}');
    }

    /**
     * @param  resource  $stream
     * @param  array<int, bool>  $firstEntryByDepth
     */
    private function writeSeparator($stream, array &$firstEntryByDepth, int $depth): void
    {
        if (! $firstEntryByDepth[$depth]) {
            fwrite($stream, ',');
        }

        $firstEntryByDepth[$depth] = false;
    }

    /**
     * @param  array<int, string>  $openParents
     * @param  array<int, string>  $segments
     */
    private function sharedDepth(array $openParents, array $segments): int
    {
        $maxDepth = min(count($openParents), count($segments));

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            if ($openParents[$depth] !== $segments[$depth]) {
                return $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * @throws JsonException
     */
    private function encode(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
