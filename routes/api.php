<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/verify', [AdminController::class, 'verify'])->middleware('admin');

Route::get('/rooms', [RoomController::class, 'index']);
Route::post('/rooms', [RoomController::class, 'store'])->middleware('admin');
Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('admin');

Route::get('/bookings/check-conflict', [BookingController::class, 'checkConflict']);
Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('admin');
