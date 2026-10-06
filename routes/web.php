<?php

use App\Http\Controllers\Admin\AdminPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminPanelController::class, 'loginForm'])->name('login');
        Route::post('login', [AdminPanelController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    });

    Route::middleware(['auth', 'role:admin'])->group(function (): void {
        Route::post('logout', [AdminPanelController::class, 'logout'])->name('logout');
        Route::get('/', [AdminPanelController::class, 'dashboard'])->name('dashboard');
        Route::get('books', [AdminPanelController::class, 'books'])->name('books');
        Route::post('books', [AdminPanelController::class, 'storeBook'])->name('books.store');
        Route::put('books/{book}', [AdminPanelController::class, 'updateBook'])->name('books.update');
        Route::delete('books/{book}', [AdminPanelController::class, 'deleteBook'])->name('books.delete');
        Route::get('users', [AdminPanelController::class, 'users'])->name('users');
        Route::patch('users/{user}/role', [AdminPanelController::class, 'updateUserRole'])->name('users.role');
    });
});
