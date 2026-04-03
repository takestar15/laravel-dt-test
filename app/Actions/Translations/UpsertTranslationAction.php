<?php

namespace App\Actions\Translations;

use App\Models\Translation;
use App\Models\TranslationTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpsertTranslationAction
{
    public function __construct(
        private readonly InvalidateTranslationCacheAction $invalidateTranslationCache,
    ) {}

    /**
     * @param  array{locale?: string, key?: string, value?: string, tags?: array<int, string>}  $attributes
     */
    public function handle(array $attributes, ?Translation $translation = null): Translation
    {
        return DB::transaction(function () use ($attributes, $translation): Translation {
            $translation ??= new Translation;

            $translation->fill(collect($attributes)->only(['locale', 'key', 'value'])->all());
            $translation->save();

            if (array_key_exists('tags', $attributes)) {
                $translation->tags()->sync($this->resolveTagIds($attributes['tags']));
            }

            $translation->load('tags');

            $this->invalidateTranslationCache->handle();

            return $translation;
        });
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, int>
     */
    private function resolveTagIds(array $tags): array
    {
        $normalizedTags = Collection::make($tags)
            ->map(fn (string $tag): string => mb_strtolower(trim($tag)))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedTags->isEmpty()) {
            return [];
        }

        $existingTagIds = TranslationTag::query()
            ->whereIn('name', $normalizedTags)
            ->pluck('id', 'name');

        $missingTags = $normalizedTags
            ->reject(fn (string $tag): bool => $existingTagIds->has($tag))
            ->map(fn (string $tag): array => [
                'name' => $tag,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($missingTags !== []) {
            TranslationTag::query()->insertOrIgnore($missingTags);
        }

        return TranslationTag::query()
            ->whereIn('name', $normalizedTags)
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }
}
