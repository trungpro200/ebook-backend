<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChapterAudioController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PersonalLibraryController;
use App\Http\Controllers\Api\ProfileController;
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
    Route::patch('me', [ProfileController::class, 'update']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::patch('reader-preferences', [ProfileController::class, 'updatePreferences']);
    Route::get('reading-progress', [PersonalLibraryController::class, 'progressIndex']);
    Route::put('books/{book}/reading-progress', [PersonalLibraryController::class, 'saveProgress']);
    Route::get('favorites', [PersonalLibraryController::class, 'favorites']);
    Route::post('books/{book}/favorite', [PersonalLibraryController::class, 'addFavorite']);
    Route::delete('books/{book}/favorite', [PersonalLibraryController::class, 'removeFavorite']);
    Route::get('bookmarks', [PersonalLibraryController::class, 'bookmarks']);
    Route::post('bookmarks', [PersonalLibraryController::class, 'saveBookmark']);
    Route::delete('bookmarks/{bookmark}', [PersonalLibraryController::class, 'removeBookmark']);

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
