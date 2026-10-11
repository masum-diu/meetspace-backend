<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\MyBookingController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/verify', [AdminController::class, 'verify'])->middleware('admin');

// Clerk sign-in + everything that needs a signed-in user
Route::post('/auth/clerk', [AuthController::class, 'clerk']);
Route::middleware('auth.token')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me/bookings', [MyBookingController::class, 'index']);
    Route::put('/me/bookings/{booking}', [MyBookingController::class, 'update']);
    Route::delete('/me/bookings/{booking}', [MyBookingController::class, 'destroy']);
    Route::post('/bookings', [BookingController::class, 'store']);
});

Route::get('/rooms', [RoomController::class, 'index']);
Route::post('/rooms', [RoomController::class, 'store'])->middleware('admin');
Route::put('/rooms/{room}', [RoomController::class, 'update'])->middleware('admin');
Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('admin');

Route::get('/bookings/check-conflict', [BookingController::class, 'checkConflict']);
Route::get('/bookings', [BookingController::class, 'index']);
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('admin');
