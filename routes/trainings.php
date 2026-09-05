<?php

use App\Http\Controllers\Trainings\AttendanceController;
use App\Http\Controllers\Trainings\RegistrationController;
use App\Http\Controllers\Trainings\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('trainings', TrainingController::class);

    // Afvinken doet de trainer; aan-/afmelden doet de speler of ouder.
    Route::patch('trainings/{training}/attendance/{player}', [AttendanceController::class, 'update'])
        ->name('attendance.update');

    Route::post('trainings/{training}/registration/{player}', [RegistrationController::class, 'store'])
        ->name('registration.store');
});
