<?php

use App\Http\Controllers\Api\AccessTokenController;
use Illuminate\Support\Facades\Route;

Route::post('tokens', AccessTokenController::class)
    ->middleware('throttle:api-tokens')
    ->name('api.tokens.store');
