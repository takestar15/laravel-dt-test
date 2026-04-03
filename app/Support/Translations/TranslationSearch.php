<?php

namespace App\Support\Translations;

use App\Models\Translation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TranslationSearch
{
    /**
     * @param  array{locale?: ?string, key?: ?string, content?: ?string, tags?: array<int, string>, per_page?: int}  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

        return Translation::query()
            ->select(['id', 'locale', 'key', 'value', 'created_at', 'updated_at'])
            ->with('tags:id,name')
            ->when($filters['locale'] ?? null, fn (Builder $query, string $locale) => $query->forLocale($locale))
            ->when($filters['key'] ?? null, fn (Builder $query, string $key) => $query->whereLike('key', '%'.$key.'%'))
            ->when($filters['content'] ?? null, fn (Builder $query, string $content) => $query->whereLike('value', '%'.$content.'%'))
            ->when($filters['tags'] ?? [], fn (Builder $query, array $tags) => $this->applyTagFilter($query, $tags))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function applyTagFilter(Builder $query, array $tags): void
    {
        $normalizedTags = Collection::make($tags)
            ->map(fn (string $tag): string => mb_strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedTags->isEmpty()) {
            return;
        }

        $query->whereHas('tags', function (Builder $tagQuery) use ($normalizedTags): void {
            $tagQuery->whereIn('name', $normalizedTags);
        }, '=', $normalizedTags->count());
    }
}
