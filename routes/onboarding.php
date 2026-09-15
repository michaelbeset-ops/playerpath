<?php

use App\Http\Controllers\Onboarding\AcceptInvitationController;
use App\Http\Controllers\Onboarding\InvitationController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\PhotoPromptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Uitnodigen: trainers en ouders, één tegelijk of een hele lijst.
    Route::post('uitnodigingen', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('uitnodigingen/{invitation}/opnieuw', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('uitnodigingen/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    // De startlijst, de rondleiding en de voorbeelddata.
    Route::post('onboarding/startlijst/gezien', [OnboardingController::class, 'dismissChecklist'])->name('onboarding.checklist.dismiss');
    Route::post('onboarding/startlijst/terug', [OnboardingController::class, 'restoreChecklist'])->name('onboarding.checklist.restore');
    Route::post('onboarding/startlijst/klaar', [OnboardingController::class, 'completeChecklist'])->name('onboarding.checklist.complete');
    Route::post('onboarding/rondleiding/klaar', [OnboardingController::class, 'finishTour'])->name('onboarding.tour.finish');
    Route::post('onboarding/rondleiding/opnieuw', [OnboardingController::class, 'restartTour'])->name('onboarding.tour.restart');
    Route::post('onboarding/rondleiding/stap', [OnboardingController::class, 'tourStep'])->name('onboarding.tour.step');
    // Het ouderscherm zoals een ouder het ziet, voor de eigenaar.
    Route::get('onboarding/ouderweergave', [OnboardingController::class, 'parentPreview'])->name('onboarding.parent-preview');
    // De keuze tussen de prestatiekaart en de inzetkaart, als stap in de rondleiding.
    Route::get('onboarding/spelerskaart', [OnboardingController::class, 'cardChoice'])->name('onboarding.card-choice');
    // Hoe een ouder zich inschrijft: de echte inschrijfpagina in een kader,
    // en de trainingen zoals een ouder ze ziet (met "Inschrijven").
    Route::get('onboarding/aanmeldpagina', [OnboardingController::class, 'enrollPreview'])->name('onboarding.enroll-preview');
    Route::get('onboarding/ouderweergave/trainingen', [OnboardingController::class, 'parentTrainingsPreview'])->name('onboarding.parent-trainings');
    Route::post('onboarding/welkom/gezien', [OnboardingController::class, 'dismissIntro'])->name('onboarding.intro.dismiss');
    // De foto, meteen na het activeren van een ouder- of speleraccount.
    Route::get('welkom/foto', [PhotoPromptController::class, 'show'])->name('onboarding.photo');
    Route::delete('onboarding/voorbeelddata', [OnboardingController::class, 'removeDemo'])->name('onboarding.demo.destroy');
});

// Een uitnodiging inwisselen. Bewust buiten de inlog: het account bestaat nog
// niet. Zie AcceptInvitationController voor waarom dat kan. De limiet houdt
// iemand die tokens zit te raden buiten de deur.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('uitnodiging/{token}', [AcceptInvitationController::class, 'show'])
        ->name('invitations.accept')
        ->where('token', '[A-Za-z0-9]{64}');
    Route::post('uitnodiging/{token}', [AcceptInvitationController::class, 'store'])
        ->where('token', '[A-Za-z0-9]{64}');
});
