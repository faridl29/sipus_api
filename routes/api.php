<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BorrowingController;
use App\Http\Controllers\Api\V1\Staff\BookController as StaffBookController;
use App\Http\Controllers\Api\V1\Staff\BorrowingController as StaffBorrowingController;
use App\Http\Controllers\Api\V1\Staff\DashboardController as StaffDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Public Catalog Routes (OPAC - Online Public Access Catalog)
    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{id}', [BookController::class, 'show']);

    // Authenticated Student Borrowing Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware('role:student')->group(function () {
            Route::post('borrowings', [BorrowingController::class, 'store']);
            Route::get('borrowings/me', [BorrowingController::class, 'myBorrowings']);
        });

        // Detail can be viewed by borrower owner (student) or staff
        Route::get('borrowings/{id}', [BorrowingController::class, 'show']);
    });

    // Staff Routes
    Route::prefix('staff')->middleware(['auth:sanctum', 'role:staff'])->group(function () {
        // Dashboard
        Route::get('dashboard', [StaffDashboardController::class, 'index']);

        // Book Management
        Route::get('books', [StaffBookController::class, 'index']);
        Route::post('books', [StaffBookController::class, 'store']);
        Route::get('books/{id}', [StaffBookController::class, 'show']);
        Route::put('books/{id}', [StaffBookController::class, 'update']);
        Route::delete('books/{id}', [StaffBookController::class, 'destroy']);

        // Borrowing Management
        Route::get('borrowings', [StaffBorrowingController::class, 'index']);
        Route::get('borrowings/history', [StaffBorrowingController::class, 'history']);
        Route::post('borrowings/direct', [StaffBorrowingController::class, 'direct']);
        Route::post('borrowings/{id}/approve', [StaffBorrowingController::class, 'approve']);
        Route::post('borrowings/{id}/reject', [StaffBorrowingController::class, 'reject']);
        Route::post('borrowings/{id}/borrow', [StaffBorrowingController::class, 'borrow']);
        Route::post('borrowings/{id}/return', [StaffBorrowingController::class, 'returnBorrowing']);
    });
});
