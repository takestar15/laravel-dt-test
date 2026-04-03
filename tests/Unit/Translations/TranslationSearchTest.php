<?php

use App\Models\Translation;
use App\Models\TranslationTag;
use App\Support\Translations\TranslationSearch;

test('translation search ignores empty tag filters after normalization', function () {
    $webTag = TranslationTag::factory()->create(['name' => 'web']);

    $first = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'dashboard.title',
        'value' => 'Dashboard',
    ]);
    $first->tags()->attach($webTag);

    $second = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'profile.title',
        'value' => 'Profile',
    ]);

    $results = app(TranslationSearch::class)->handle([
        'locale' => 'en',
        'tags' => [' ', '', '   '],
        'per_page' => 50,
    ]);

    expect($results->total())->toBe(2);
    expect(collect($results->items())->pluck('key')->all())
        ->toBe([$second->key, $first->key]);
});
