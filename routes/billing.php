<?php

use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\MyBillingController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\ProductController;
use App\Http\Controllers\Billing\PublicCheckoutController;
use App\Http\Controllers\Billing\PurchaseController;
use App\Http\Controllers\Billing\ShopController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\Media\ProductImageController;
use App\Http\Controllers\Offerings\ParticipantController;
use App\Http\Controllers\Offerings\SlotController;
use Illuminate\Support\Facades\Route;

// De hele financiële kant hangt aan één feature: staat betalingen uit, dan
// bestaan tarieven, abonnementen, facturen en de betaalpagina van de ouder
// simpelweg niet voor die school.
Route::middleware(['auth', 'verified', 'feature:betalingen'])->group(function () {
    // Het aanbod van de school. Beheer: alleen de eigenaar, zie de policies.
    // De routenamen blijven products.*: in code heet dit Product, in de taal van
    // de school heet het Aanbod (CLAUDE.md 3.3).
    Route::resource('aanbod', ProductController::class)
        ->parameters(['aanbod' => 'product'])
        ->names('products')
        ->except(['show']);

    // Dat adres stond in bladwijzers voordat dit Aanbod heette.
    Route::get('products', fn () => redirect()->route('products.index'));

    // Wie er meedoet en wie er wacht. De school beslist zelf wie er doorschuift.
    // De afbeelding bij een aanbod, voor in de shop. Optioneel.
    Route::post('aanbod/{product}/foto', [ProductImageController::class, 'store'])->name('products.image.store');
    Route::delete('aanbod/{product}/foto', [ProductImageController::class, 'destroy'])->name('products.image.destroy');

    Route::get('aanbod/{product}/deelnemers', [ParticipantController::class, 'index'])->name('offerings.participants');
    Route::post('aanbod/{product}/deelnemers/{participation}/plek', [ParticipantController::class, 'promote'])->name('offerings.promote');
    Route::delete('aanbod/{product}/deelnemers/{participation}', [ParticipantController::class, 'cancel'])->name('offerings.cancel');

    // Beschikbare momenten bij een privétraining: de school zet ze neer, een
    // ouder boekt er een in de shop.
    Route::get('aanbod/{product}/momenten', [SlotController::class, 'index'])->name('offerings.slots');
    Route::post('aanbod/{product}/momenten', [SlotController::class, 'store'])->name('offerings.slots.store');
    Route::delete('aanbod/{product}/momenten/{slot}', [SlotController::class, 'destroy'])->name('offerings.slots.destroy');

    // Een product toekennen aan een speler. Staat op de pagina van die speler,
    // want daar zit je als een ouder om een rittenkaart vraagt.
    Route::post('players/{player}/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::delete('players/{player}/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::patch('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');

    // Voor ouder en speler: het eigen abonnement.
    Route::get('billing', [MyBillingController::class, 'index'])->name('billing.index');

    // De shop: wat een ouder zelf kan afnemen. Dezelfde prijslijst als bij
    // Producten, min de abonnementen.
    Route::get('shop', [ShopController::class, 'index'])->name('shop.index');
    Route::post('shop/{product}', [ShopController::class, 'store'])->name('shop.store');

    // Fase 9: zelf betalen. De uitkomst komt via de webhook binnen, niet hier.
    Route::post('billing/payments/{payment}/betalen', [CheckoutController::class, 'pay'])->name('billing.pay');
    Route::get('billing/payments/{payment}/terug', [CheckoutController::class, 'return'])->name('billing.return');
});

/*
 * Afrekenen zonder inlog, via een ondertekende link uit een e-mail. Bedoeld
 * voor het gezin dat net is ingeschreven en nog geen wachtwoord heeft gekozen.
 * De handtekening is hier het slot; zie Support\Payments\PaymentLink.
 */
Route::middleware('signed')->group(function () {
    Route::get('betalen/{payment}', [PublicCheckoutController::class, 'show'])->name('public-pay.show');
    Route::post('betalen/{payment}', [PublicCheckoutController::class, 'pay'])->name('public-pay.pay');
    Route::get('betalen/{payment}/terug', [PublicCheckoutController::class, 'return'])->name('public-pay.return');
});

/*
 * De webhook van de betaalprovider. Geen auth en geen sessie: dit is
 * server-naar-server. Zie WebhookController voor waarom hij altijd 200 geeft.
 */
Route::post('webhooks/mollie', WebhookController::class)->name('webhooks.mollie');
