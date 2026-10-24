<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MockOtaController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\SyncLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::get('/simulator', [SimulatorController::class, 'index']);

// Sync Logs
Route::get('/logs', [SyncLogController::class, 'index']);
Route::post('/logs/{syncLog}/retry', [SyncLogController::class, 'retry']);

// Channel Management & OTA Comparison
Route::get('/channels', [ChannelController::class, 'index']);
Route::post('/channels/{channel}/sync', [ChannelController::class, 'fullSync']);

// Mock OTA Endpoints
Route::post('/mock/{channel}/inventory', [MockOtaController::class, 'updateInventory']);
Route::get('/mock/{channel}/state', [MockOtaController::class, 'getState']);
