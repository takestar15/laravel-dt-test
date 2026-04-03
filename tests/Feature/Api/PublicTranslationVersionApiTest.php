<?php

use App\Models\Translation;
use App\Models\TranslationTag;

test('public translation version endpoint returns the current version', function () {
    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'checkout.submit',
        'value' => 'Pay now',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'web']));

    $response = $this->getJson(route('api.public.translations.version'))
        ->assertOk()
        ->assertJsonPath('version', 1);

    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public');
    expect($cacheControl)->toContain('max-age=30');
    expect($cacheControl)->toContain('s-maxage=30');
    expect($cacheControl)->toContain('stale-while-revalidate=30');
});

test('public export endpoint returns cacheable translation json without authentication', function () {
    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'checkout.submit',
        'value' => 'Pay now',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'web']));

    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'checkout.cancel',
        'value' => 'Cancel',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'mobile']));

    $response = $this->get(route('api.public.translations.export', [
        'locale' => 'en',
        'tags' => ['web'],
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('ETag')
        ->assertHeader('X-Translation-Version', '1')
        ->assertSee('{"checkout":{"submit":"Pay now"}}', false);

    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public');
    expect($cacheControl)->toContain('max-age=60');
    expect($cacheControl)->toContain('s-maxage=300');
    expect($cacheControl)->toContain('stale-while-revalidate=30');
});

test('public export endpoint returns not modified when etag matches', function () {
    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'checkout.submit',
        'value' => 'Pay now',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'web']));

    $initialResponse = $this->get(route('api.public.translations.export', [
        'locale' => 'en',
        'tags' => ['web'],
    ]));

    $etag = $initialResponse->headers->get('ETag');

    $this->withHeader('If-None-Match', (string) $etag)
        ->get(route('api.public.translations.export', [
            'locale' => 'en',
            'tags' => ['web'],
        ]))
        ->assertStatus(304);
});
