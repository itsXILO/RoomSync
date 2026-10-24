<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MockOtaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);

// Mock OTA Endpoints
Route::post('/mock/{channel}/inventory', [MockOtaController::class, 'updateInventory']);
Route::get('/mock/{channel}/state', [MockOtaController::class, 'getState']);
