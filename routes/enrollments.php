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

/*
 * De openbare aanmeldpagina: zonder inlog, de school uit de slug.
 *
 * Bedoeld om vanaf de eigen website van de school te linken of in een iframe te
 * zetten; zie SecurityHeaders voor waarom dat laatste hier mag. Met ?aanbod=12
 * opent hij meteen dat aanbod, zodat een school naast elk programma op haar
 * site een eigen knop kan zetten.
 *
 * Beperkt tot 10 inzendingen per minuut per adres tegen spam.
 */
Route::get('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'show'])->name('enroll.show');

// Hetzelfde, maar dan op het subdomein van de school: keepersschool.playerpath.nl
// Zonder APP_DOMAIN of zonder subdomein bestaat dit adres niet.
Route::get('inschrijven', [PublicEnrollmentController::class, 'onSubdomain'])->name('enroll.subdomain');
Route::post('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enroll.store');
