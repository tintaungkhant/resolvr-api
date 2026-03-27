<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Agent\AuthController;
use App\Http\Controllers\Api\V1\Agent\UserController;

Route::prefix('v1')->group(function () {
    Route::prefix('agent')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'abilities:role:agent'])->group(function () {
            Route::get('user', [UserController::class, 'show']);
        });
    });
});
