<?php

use App\Actions\Translations\InvalidateTranslationCacheAction;
use App\Actions\Translations\UpsertTranslationAction;
use App\Models\Translation;
use App\Models\TranslationTag;
use App\Support\Translations\TranslationExport;

test('translation export returns locale payloads keyed by translation key', function () {
    $tag = TranslationTag::factory()->create(['name' => 'web']);
    $translation = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'home.title',
        'value' => 'Home',
    ]);
    $translation->tags()->attach($tag);

    $payload = app(TranslationExport::class)->handle([
        'locale' => 'en',
        'tags' => ['web'],
    ]);

    expect($payload)->toBe([
        'home' => [
            'title' => 'Home',
        ],
    ]);
});

test('cache invalidation increments the export version', function () {
    $action = app(InvalidateTranslationCacheAction::class);
    $version = $action->currentVersion();

    expect($action->handle())->toBe($version + 1);
});

test('export returns a newly added translation after the cache has been warmed', function () {
    $tag = TranslationTag::factory()->create(['name' => 'web']);

    Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'home.title',
        'value' => 'Accueil',
    ])->tags()->attach($tag);

    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'home.subtitle',
        'value' => 'Welcome back',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'mobile']));

    $initialPayload = app(TranslationExport::class)->handle([
        'locale' => 'en',
        'tags' => ['web'],
    ]);

    expect($initialPayload)->toBe([]);

    app(UpsertTranslationAction::class)->handle([
        'locale' => 'en',
        'key' => 'checkout.submit',
        'value' => 'Pay now',
        'tags' => ['web'],
    ]);

    $refreshedPayload = app(TranslationExport::class)->handle([
        'locale' => 'en',
        'tags' => ['web'],
    ]);

    expect($refreshedPayload)->toBe([
        'checkout' => [
            'submit' => 'Pay now',
        ],
    ]);
});

test('inserting a translation makes it available in the export for its locale and tags', function () {
    Translation::factory()->create([
        'locale' => 'es',
        'key' => 'billing.invoice.email',
        'value' => 'Enviar factura',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'mobile']));

    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'billing.invoice.download',
        'value' => 'Download invoice',
    ])->tags()->attach(TranslationTag::factory()->create(['name' => 'web']));

    $translation = app(UpsertTranslationAction::class)->handle([
        'locale' => 'es',
        'key' => 'billing.invoice.download',
        'value' => 'Descargar factura',
        'tags' => [' Web ', 'PORTAL'],
    ]);

    expect($translation->exists)->toBeTrue();
    expect($translation->tags->pluck('name')->all())->toBe(['portal', 'web']);

    $payload = app(TranslationExport::class)->handle([
        'locale' => 'es',
        'tags' => ['web', 'portal'],
    ]);

    expect($payload)->toBe([
        'billing' => [
            'invoice' => [
                'download' => 'Descargar factura',
            ],
        ],
    ]);
});

test('json export serializes matching translations without loading unrelated records', function () {
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

    $payload = app(TranslationExport::class)->toJson([
        'locale' => 'en',
        'tags' => ['web'],
    ]);

    expect($payload)->toBe('{"checkout":{"submit":"Pay now"}}');
});
