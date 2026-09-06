<?php

use App\Http\Controllers\Enrollments\EnrollmentController;
use App\Http\Controllers\Enrollments\PublicEnrollmentController;
use Illuminate\Support\Facades\Route;

// De inbox van de eigenaar.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('feature:inschrijvingen')->name('enrollments.index');
    Route::post('enrollments/{enrollment}/approve', [EnrollmentController::class, 'approve'])->name('enrollments.approve');
    Route::post('enrollments/{enrollment}/decline', [EnrollmentController::class, 'decline'])->name('enrollments.decline');
});

// Het openbare inschrijfformulier: zonder inlog, de school uit de slug.
// Beperkt tot 10 inzendingen per minuut per adres tegen spam.
Route::get('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'show'])->name('enroll.show');
Route::post('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enroll.store');
