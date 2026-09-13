<?php

use App\Http\Controllers\Api\Incubatee\AuthController;
use App\Http\Controllers\Api\Incubatee\DashboardController;
use App\Http\Controllers\Api\Incubatee\DocumentsController;
use App\Http\Controllers\Api\Incubatee\LearnController;
use App\Http\Controllers\Api\Incubatee\MentorshipController;
use Illuminate\Support\Facades\Route;

Route::prefix('incubatee')->name('api.incubatee.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('login');

    Route::middleware(['auth:sanctum', 'incubatee_api'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('mentorship', [MentorshipController::class, 'index'])->name('mentorship.index');
        Route::post('mentorship', [MentorshipController::class, 'store'])
            ->middleware('throttle:15,1')
            ->name('mentorship.store');
        Route::post('mentorship/{mentorshipRequest}/cancel', [MentorshipController::class, 'cancel'])
            ->middleware('throttle:15,1')
            ->name('mentorship.cancel');
        Route::get('learn', [LearnController::class, 'show'])->name('learn');
        Route::get('documents', [DocumentsController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}/download', [DocumentsController::class, 'download'])
            ->middleware('throttle:30,1')
            ->name('documents.download');
    });
});
