<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Client\AuthController;
use App\Http\Controllers\Api\V1\Client\TicketController;
use App\Http\Controllers\Api\V1\Client\ProfileController;

Route::prefix('v1')->group(function () {
    Route::prefix('client')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'abilities:role:client'])->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);

            Route::post('tickets', [TicketController::class, 'store']);
        });
    });
});
