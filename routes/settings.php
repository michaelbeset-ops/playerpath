<?php

use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Middleware\RedirectKindAccount;
use Illuminate\Support\Facades\Route;

// Een kind-account (via de kind-link) heeft geen echt e-mailadres en geen
// wachtwoord dat iemand kent: daar valt niets in te stellen.
Route::middleware(['auth', RedirectKindAccount::class])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.settings.update');

    // Fase 10: welke mail wil je ontvangen. Meldingen in de app staan altijd aan.
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');
});
