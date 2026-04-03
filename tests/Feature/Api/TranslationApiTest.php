<?php

use App\Models\Translation;
use App\Models\TranslationTag;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function apiToken(User $user): string
{
    return $user->createToken('test-suite')->plainTextToken;
}

test('translations require authentication', function () {
    $this->getJson(route('api.translations.index'))
        ->assertUnauthorized();
});

test('users can create and view translations with tags', function () {
    $user = User::factory()->create();

    $storeResponse = $this
        ->withToken(apiToken($user))
        ->postJson(route('api.translations.store'), [
            'locale' => 'en',
            'key' => 'dashboard.welcome',
            'value' => 'Welcome back',
            'tags' => ['web', 'desktop'],
        ]);

    $storeResponse->assertCreated();

    $translationId = $storeResponse->json('data.id');

    $this->withToken(apiToken($user))
        ->getJson(route('api.translations.show', $translationId))
        ->assertSuccessful()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.key', 'dashboard.welcome')
        ->assertJsonPath('data.value', 'Welcome back')
        ->assertJsonPath('data.tags.0', 'desktop')
        ->assertJsonPath('data.tags.1', 'web');

    $translation = Translation::query()->firstOrFail();

    expect($translation->tags->pluck('name')->all())->toBe(['desktop', 'web']);
});

test('users can update translations and refresh export payloads', function () {
    $user = User::factory()->create();
    $mobileTag = TranslationTag::factory()->create(['name' => 'mobile']);
    $translation = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'profile.title',
        'value' => 'Profile',
    ]);
    $translation->tags()->attach($mobileTag);

    $before = $this->withToken(apiToken($user))
        ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['mobile']]));

    $before->assertSuccessful()
        ->assertJsonPath('profile.title', 'Profile');

    $this->withToken(apiToken($user))
        ->patchJson(route('api.translations.update', $translation), [
            'value' => 'My Profile',
            'tags' => ['mobile', 'web'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.value', 'My Profile');

    $after = $this->withToken(apiToken($user))
        ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['mobile']]));

    $after->assertSuccessful()
        ->assertJsonPath('profile.title', 'My Profile');
});

test('users can search translations by locale key content and tags', function () {
    $user = User::factory()->create();

    $checkout = Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'checkout.submit',
        'value' => 'Payer maintenant',
    ]);
    $checkout->tags()->attach(TranslationTag::factory()->create(['name' => 'mobile']));

    $other = Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'account.cancel',
        'value' => 'Annuler',
    ]);
    $other->tags()->attach(TranslationTag::factory()->create(['name' => 'web']));

    $response = $this->withToken(apiToken($user))
        ->getJson(route('api.translations.index', [
            'locale' => 'fr',
            'key' => 'checkout',
            'content' => 'Payer',
            'tags' => ['mobile'],
        ]));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'checkout.submit');
});

test('export endpoint returns grouped locales when no locale filter is provided', function () {
    $user = User::factory()->create();

    Translation::factory()->create([
        'locale' => 'en',
        'key' => 'common.ok',
        'value' => 'OK',
    ]);

    Translation::factory()->create([
        'locale' => 'es',
        'key' => 'common.ok',
        'value' => 'Vale',
    ]);

    $this->withToken(apiToken($user))
        ->getJson(route('api.translations.export'))
        ->assertSuccessful()
        ->assertJsonPath('en.common.ok', 'OK')
        ->assertJsonPath('es.common.ok', 'Vale');
});

test('export responses stay under five hundred milliseconds after cache warmup', function () {
    $user = User::factory()->create();
    $tag = TranslationTag::factory()->create(['name' => 'web']);

    Translation::factory()->count(2500)->create([
        'locale' => 'en',
    ])->each(fn (Translation $translation) => $translation->tags()->attach($tag));

    $this->withToken(apiToken($user))
        ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['web']]))
        ->assertSuccessful();

    $start = hrtime(true);

    $this->withToken(apiToken($user))
        ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['web']]))
        ->assertSuccessful();

    $elapsedMilliseconds = (hrtime(true) - $start) / 1_000_000;

    expect($elapsedMilliseconds)->toBeLessThan(500.0);
});

test('seed command can create a large translation batch', function () {
    Artisan::call('translations:seed', [
        '--count' => 250,
        '--chunk' => 50,
    ]);

    expect(Translation::query()->count())->toBe(250);
});
