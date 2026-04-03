<?php

use App\Http\Controllers\Api\AccessTokenController;
use App\Http\Controllers\Api\PublicTranslationExportController;
use App\Http\Controllers\Api\PublicTranslationVersionController;
use App\Http\Controllers\Api\TranslationController;
use Illuminate\Support\Facades\Route;

Route::post('tokens', AccessTokenController::class)
    ->middleware('throttle:api-tokens')
    ->name('api.tokens.store');

Route::get('public/translations/export', PublicTranslationExportController::class)
    ->middleware('throttle:public-translations')
    ->name('api.public.translations.export');

Route::get('public/translations/version', PublicTranslationVersionController::class)
    ->middleware('throttle:public-translations')
    ->name('api.public.translations.version');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('translations/export', [TranslationController::class, 'export'])
        ->name('api.translations.export');

    Route::apiResource('translations', TranslationController::class)
        ->names('api.translations')
        ->only(['index', 'show', 'store', 'update']);
});
