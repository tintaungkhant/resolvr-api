<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Client\AuthController;
use App\Http\Controllers\Api\V1\Client\UserController;

Route::prefix('v1')->group(function () {
    Route::prefix('client')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', [UserController::class, 'show']);
        });
    });
});
