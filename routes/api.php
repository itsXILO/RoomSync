<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{channel}/bookings', [BookingController::class, 'store']);
Route::post('/webhooks/{channel}/cancellations', [BookingController::class, 'cancel']);
