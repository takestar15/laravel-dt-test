<?php

use App\Models\Translation;
use App\Models\TranslationTag;
use App\Models\User;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;

function performanceApiToken(User $user): string
{
    return $user->createToken('performance-suite')->plainTextToken;
}

function performanceEnabled(): bool
{
    return filter_var(env('RUN_PERFORMANCE_TESTS', false), FILTER_VALIDATE_BOOL);
}

function performanceIterations(): int
{
    return max((int) env('PERF_ITERATIONS', 5), 1);
}

function performanceBudget(string $metric): float
{
    return match ($metric) {
        'api' => (float) env('PERF_API_BUDGET_MS', 200),
        'export' => (float) env('PERF_EXPORT_BUDGET_MS', 500),
        default => throw new InvalidArgumentException("Unknown performance metric [{$metric}]."),
    };
}

beforeEach(function (): void {
    if (! performanceEnabled()) {
        $this->markTestSkipped('Performance tests are opt-in. Set RUN_PERFORMANCE_TESTS=true to run them.');
    }
});

test('core api endpoints stay under two hundred milliseconds on warm requests', function () {
    $user = User::factory()->create([
        'email' => 'perf@example.com',
        'password' => 'password',
    ]);

    $token = performanceApiToken($user);
    $webTag = TranslationTag::factory()->create(['name' => 'web']);
    $translation = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'performance.profile.title',
        'value' => 'Profile',
    ]);
    $translation->tags()->attach($webTag);

    $requests = [
        'issue_access_token' => function () use ($user): TestResponse {
            return $this->postJson(route('api.tokens.store'), [
                'email' => $user->email,
                'password' => 'password',
                'device_name' => 'postman',
            ])->assertSuccessful();
        },
        'search_translations' => function () use ($token): TestResponse {
            return $this->withToken($token)
                ->getJson(route('api.translations.index', [
                    'locale' => 'en',
                    'tags' => ['web'],
                ]))
                ->assertSuccessful();
        },
        'show_translation' => function () use ($token, $translation): TestResponse {
            return $this->withToken($token)
                ->getJson(route('api.translations.show', $translation))
                ->assertSuccessful();
        },
        'authenticated_export' => function () use ($token): TestResponse {
            return $this->withToken($token)
                ->getJson(route('api.translations.export', [
                    'locale' => 'en',
                    'tags' => ['web'],
                ]))
                ->assertSuccessful();
        },
        'public_export' => function (): TestResponse {
            return $this->get(route('api.public.translations.export', [
                'locale' => 'en',
                'tags' => ['web'],
            ]))->assertOk();
        },
        'public_version' => function (): TestResponse {
            return $this->getJson(route('api.public.translations.version'))
                ->assertOk();
        },
    ];

    foreach ($requests as $request) {
        $request();
    }

    $results = [];

    foreach ($requests as $name => $request) {
        $results[$name] = Benchmark::measure($request, iterations: performanceIterations());
    }

    $storeMilliseconds = Benchmark::measure(function () use ($token): void {
        $this->withToken($token)
            ->postJson(route('api.translations.store'), [
                'locale' => 'en',
                'key' => 'performance.dashboard.'.str()->uuid(),
                'value' => 'Fast response',
                'tags' => ['web'],
            ])
            ->assertCreated();
    }, iterations: performanceIterations());

    $updateTranslation = Translation::factory()->create([
        'locale' => 'en',
        'key' => 'performance.settings.title',
        'value' => 'Settings',
    ]);
    $updateTranslation->tags()->attach($webTag);

    $this->withToken($token)
        ->patchJson(route('api.translations.update', $updateTranslation), [
            'value' => 'My Settings',
            'tags' => ['web'],
        ])
        ->assertSuccessful();

    $updateMilliseconds = Benchmark::measure(function () use ($token, $updateTranslation): void {
        $this->withToken($token)
            ->patchJson(route('api.translations.update', $updateTranslation), [
                'value' => 'My Settings '.str()->uuid(),
                'tags' => ['web'],
            ])
            ->assertSuccessful();
    }, iterations: performanceIterations());

    $results['store_translation'] = $storeMilliseconds;
    $results['update_translation'] = $updateMilliseconds;

    foreach ($results as $endpoint => $elapsedMilliseconds) {
        expect($elapsedMilliseconds, "{$endpoint} exceeded 200ms with {$elapsedMilliseconds}ms")
            ->toBeLessThan(performanceBudget('api'));
    }
})->group('performance');

test('large translation exports stay under five hundred milliseconds after cache warmup', function () {
    $user = User::factory()->create();
    $token = performanceApiToken($user);
    Artisan::call('translations:seed', [
        '--count' => 100000,
        '--chunk' => 1000,
    ]);

    $this->withToken($token)
        ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['web']]))
        ->assertSuccessful();

    $authenticatedExportMilliseconds = Benchmark::measure(function () use ($token): void {
        $this->withToken($token)
            ->getJson(route('api.translations.export', ['locale' => 'en', 'tags' => ['web']]))
            ->assertSuccessful();
    }, iterations: performanceIterations());

    $this->get(route('api.public.translations.export', ['locale' => 'en', 'tags' => ['web']]))
        ->assertOk();

    $publicExportMilliseconds = Benchmark::measure(function (): void {
        $this->get(route('api.public.translations.export', ['locale' => 'en', 'tags' => ['web']]))
            ->assertOk();
    }, iterations: performanceIterations());

    expect($authenticatedExportMilliseconds)->toBeLessThan(performanceBudget('export'));
    expect($publicExportMilliseconds)->toBeLessThan(performanceBudget('export'));
})->group('performance');
