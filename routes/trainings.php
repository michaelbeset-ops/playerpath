<?php

use App\Http\Controllers\Trainings\AttendanceController;
use App\Http\Controllers\Trainings\CalendarController;
use App\Http\Controllers\Trainings\CancellationController;
use App\Http\Controllers\Trainings\MyTrainingsController;
use App\Http\Controllers\Trainings\RegistrationController;
use App\Http\Controllers\Trainings\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('feature:kalender')->get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    // Mijn trainingen. Vóór de resource, anders vangt {training} het pad
    // "mijn" op en krijg je een 404 op een training die niet bestaat.
    Route::get('trainings/mijn', MyTrainingsController::class)->name('trainings.mine');

    Route::resource('trainings', TrainingController::class);

    // Afvinken doet de trainer; aan-/afmelden doet de speler of ouder.
    Route::patch('trainings/{training}/attendance/{player}', [AttendanceController::class, 'update'])
        ->name('attendance.update');

    Route::post('trainings/{training}/registration/{player}', [RegistrationController::class, 'store'])
        ->name('registration.store');

    // Fase 10: afzeggen stuurt meteen bericht aan de groep.
    Route::post('trainings/{training}/afzeggen', [CancellationController::class, 'store'])->name('trainings.cancel');
    Route::delete('trainings/{training}/afzeggen', [CancellationController::class, 'destroy'])->name('trainings.uncancel');
});
