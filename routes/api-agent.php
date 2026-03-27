<?php

use App\Http\Controllers\Api\V1\Agent\AuthController;
use App\Http\Controllers\Api\V1\Agent\ProfileController;
use App\Http\Controllers\Api\V1\Agent\TicketController;
use App\Http\Controllers\Api\V1\Agent\TicketMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('agent')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'abilities:role:agent'])->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);

            Route::get('tickets', [TicketController::class, 'index']);
            Route::get('tickets/{ticket}', [TicketController::class, 'show']);
            Route::patch('tickets/{ticket}/priority', [TicketController::class, 'updatePriority']);
            Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
            
            Route::get('tickets/{ticket}/messages', [TicketMessageController::class, 'index']);
            Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store']);
        });
    });
});
