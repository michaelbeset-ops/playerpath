<?php

use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\MyBillingController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Beheer: alleen de eigenaar, zie de policies.
    Route::resource('plans', PlanController::class)->except(['show']);

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::patch('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');

    // Voor ouder en speler: het eigen abonnement.
    Route::get('billing', [MyBillingController::class, 'index'])->name('billing.index');

    // Fase 9: zelf betalen. De uitkomst komt via de webhook binnen, niet hier.
    Route::post('billing/payments/{payment}/betalen', [CheckoutController::class, 'pay'])->name('billing.pay');
    Route::get('billing/payments/{payment}/terug', [CheckoutController::class, 'return'])->name('billing.return');
});

/*
 * De webhook van de betaalprovider. Geen auth en geen sessie: dit is
 * server-naar-server. Zie WebhookController voor waarom hij altijd 200 geeft.
 */
Route::post('webhooks/mollie', WebhookController::class)->name('webhooks.mollie');
