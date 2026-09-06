<?php

use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\SchoolController;
use App\Http\Controllers\Platform\SchoolUserController;
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
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('scholen', [SchoolController::class, 'index'])->name('schools.index');
        Route::get('scholen/nieuw', [SchoolController::class, 'create'])->name('schools.create');
        Route::post('scholen', [SchoolController::class, 'store'])->name('schools.store');
        Route::get('scholen/{school}', [SchoolController::class, 'show'])->name('schools.show');
        Route::get('scholen/{school}/bewerken', [SchoolController::class, 'edit'])->name('schools.edit');
        Route::patch('scholen/{school}', [SchoolController::class, 'update'])->name('schools.update');
        Route::patch('scholen/{school}/status', [SchoolController::class, 'toggle'])->name('schools.toggle');

        // Functies en gebruikers van één school.
        Route::patch('scholen/{school}/functies', [SchoolUserController::class, 'features'])->name('schools.features');
        Route::get('scholen/{school}/gebruikers', [SchoolUserController::class, 'index'])->name('schools.users');
        Route::post('scholen/{school}/gebruikers', [SchoolUserController::class, 'store'])->name('schools.users.store');
        Route::patch('scholen/{school}/gebruikers/{user}/status', [SchoolUserController::class, 'toggle'])->name('schools.users.toggle');
        Route::post('scholen/{school}/gebruikers/{user}/wachtwoord', [SchoolUserController::class, 'reset'])->name('schools.users.reset');

        // Bekijken als een gebruiker van die school. De uitgang staat hier
        // bewust niet: zie hieronder.
        Route::post('gebruikers/{user}/bekijken', [ImpersonationController::class, 'store'])->name('impersonate');
    });

/*
 * Terugkeren uit "bekijken als".
 *
 * Buiten de groep hierboven, want EnterPlatform weigert verzoeken zolang je
 * aan het kijken bent — dan zou de uitgang achter de deur liggen die hij zelf
 * op slot doet. De controller controleert zelf of er iets te verlaten valt en
 * of degene die terugkomt echt de platformbeheerder was.
 */
Route::middleware('auth')
    ->post('stop-bekijken', [ImpersonationController::class, 'destroy'])
    ->name('impersonate.stop');
