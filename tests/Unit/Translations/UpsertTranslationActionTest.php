<?php

use App\Actions\Translations\UpsertTranslationAction;
use App\Models\Translation;
use App\Models\TranslationTag;

test('updating a translation without tags preserves its existing tags', function () {
    $existingTag = TranslationTag::factory()->create(['name' => 'web']);
    $translation = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'account.title',
        'value' => 'Account',
    ]);
    $translation->tags()->attach($existingTag);

    $updated = app(UpsertTranslationAction::class)->handle([
        'value' => 'My Account',
    ], $translation);

    expect($updated->value)->toBe('My Account');
    expect($updated->tags->pluck('name')->all())->toBe(['web']);
});

test('syncing only existing tags clears blank tags without creating new ones', function () {
    $existingTag = TranslationTag::factory()->create(['name' => 'web']);

    $translation = app(UpsertTranslationAction::class)->handle([
        'locale' => 'en',
        'key' => 'checkout.title',
        'value' => 'Checkout',
        'tags' => ['web'],
    ]);

    expect($translation->tags->pluck('name')->all())->toBe(['web']);

    $updated = app(UpsertTranslationAction::class)->handle([
        'tags' => [' ', '', '   '],
    ], $translation);

    expect($updated->tags)->toHaveCount(0);
    expect(TranslationTag::query()->pluck('name')->all())->toBe([$existingTag->name]);
});
