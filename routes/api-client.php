<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Client\AuthController;
use App\Http\Controllers\Api\V1\Client\AgentController;
use App\Http\Controllers\Api\V1\Client\TicketController;
use App\Http\Controllers\Api\V1\Client\ProfileController;
use App\Http\Controllers\Api\V1\Client\TicketMessageController;

Route::prefix('v1')->group(function () {
    Route::prefix('client')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'abilities:role:client'])->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::get('agents', [AgentController::class, 'index']);

            Route::get('tickets', [TicketController::class, 'index']);
            Route::post('tickets', [TicketController::class, 'store']);
            Route::get('tickets/{ticket}', [TicketController::class, 'show']);
            Route::patch('tickets/{ticket}/priority', [TicketController::class, 'updatePriority']);

            Route::get('tickets/{ticket}/messages', [TicketMessageController::class, 'index']);
            Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store']);
        });
    });
});
