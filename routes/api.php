<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::apiResource('books', BookController::class)->only(['index', 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
