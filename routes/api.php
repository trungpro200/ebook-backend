<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChapterAudioController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\HomeController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('home', HomeController::class);
Route::get('categories', [CategoryController::class, 'index']);
Route::apiResource('books', BookController::class)->only(['index', 'show']);
Route::get('chapters/{chapter}', [ChapterController::class, 'show']);
Route::post('chapters/{chapter}/audio', [ChapterAudioController::class, 'store'])->middleware('throttle:30,1');
Route::get('chapters/{chapter}/audio', [ChapterAudioController::class, 'status']);
Route::get('chapters/{chapter}/audio/file', [ChapterAudioController::class, 'file'])->name('chapters.audio.file');
Route::get('chapters/{chapter}/audio/segments/{index}', [ChapterAudioController::class, 'segment'])->whereNumber('index')->name('chapters.audio.segment');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
