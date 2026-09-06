<?php

use App\Http\Controllers\Platform\SchoolController;
use App\Http\Middleware\EnterPlatform;
use Illuminate\Support\Facades\Route;

/*
 * De beheeromgeving van het platform.
 *
 * Alles hierbinnen draait met de school-scope open; buiten deze groep blijft
 * die fail-closed. Dat openzetten gebeurt in EnterPlatform, samen met de
 * rolcontrole, zodat er geen route kan bestaan die het één wel doet en het
 * ander niet.
 *
 * Het pad is Nederlands (/beheer) net als de rest van de app, en geeft een 404
 * aan wie er niet hoort: dat deze omgeving bestaat is niets wat een
 * schooleigenaar hoeft te weten.
 */
Route::middleware(['auth', 'verified', EnterPlatform::class])
    ->prefix('beheer')
    ->name('platform.')
    ->group(function () {
        Route::redirect('/', '/beheer/scholen');

        Route::get('scholen', [SchoolController::class, 'index'])->name('schools.index');
        Route::get('scholen/nieuw', [SchoolController::class, 'create'])->name('schools.create');
        Route::post('scholen', [SchoolController::class, 'store'])->name('schools.store');
        Route::get('scholen/{school}', [SchoolController::class, 'show'])->name('schools.show');
        Route::get('scholen/{school}/bewerken', [SchoolController::class, 'edit'])->name('schools.edit');
        Route::patch('scholen/{school}', [SchoolController::class, 'update'])->name('schools.update');
        Route::patch('scholen/{school}/status', [SchoolController::class, 'toggle'])->name('schools.toggle');
    });
