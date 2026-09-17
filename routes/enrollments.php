<?php

use App\Http\Controllers\Billing\FamilyEnrollmentController;
use App\Http\Controllers\Enrollments\EnrollmentController;
use App\Http\Controllers\Enrollments\PublicEnrollmentController;
use App\Models\SchoolSlugRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// De inbox van de eigenaar.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('feature:inschrijvingen')->name('enrollments.index');
    Route::post('enrollments/{enrollment}/approve', [EnrollmentController::class, 'approve'])->name('enrollments.approve');
    Route::post('enrollments/{enrollment}/decline', [EnrollmentController::class, 'decline'])->name('enrollments.decline');
    Route::get('enrollments/{enrollment}/betaallink', [EnrollmentController::class, 'paymentLink'])->name('enrollments.payment-link');
    Route::post('enrollments/{enrollment}/annuleren', [EnrollmentController::class, 'cancel'])->name('enrollments.cancel');

    // Wat een ouder zelf kan: annuleren vóór de start (restitutiebeleid) en
    // een abonnement opzeggen (opzegtermijn). Alleen de eigen kinderen.
    Route::post('billing/inschrijvingen/{enrollment}/annuleren', [FamilyEnrollmentController::class, 'cancel'])->name('billing.enrollments.cancel');
    Route::post('billing/abonnementen/{subscription}/opzeggen', [FamilyEnrollmentController::class, 'cancelSubscription'])->name('billing.subscriptions.cancel');
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
 *
 * Een onbekende slug die ooit van een school was stuurt permanent door naar
 * haar huidige adres (zie SchoolSlugRedirect): 301 voor het openen, 308 voor
 * de formulieren, zodat een POST een POST blijft. Anders een 404.
 */
$oudAdres = function (Request $request) {
    $slug = (string) $request->route()->originalParameter('school');
    $school = SchoolSlugRedirect::schoolFor($slug);

    abort_if($school === null, 404);

    $parameters = [...$request->route()->originalParameters(), 'school' => $school->slug];
    $url = route($request->route()->getName(), $parameters);

    if ($request->getQueryString() !== null) {
        $url .= '?'.$request->getQueryString();
    }

    return redirect()->to($url, $request->isMethod('GET') ? 301 : 308);
};

Route::get('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'show'])->missing($oudAdres)->name('enroll.show');

// Hetzelfde, maar dan op het subdomein van de school: keepersschool.playerpath.nl
// Zonder APP_DOMAIN of zonder subdomein bestaat dit adres niet.
Route::get('inschrijven', [PublicEnrollmentController::class, 'onSubdomain'])->name('enroll.subdomain');
Route::post('inschrijven/{school:slug}', [PublicEnrollmentController::class, 'store'])
    ->missing($oudAdres)
    ->middleware('throttle:10,1,enroll-store')
    ->name('enroll.store');

// Het overzicht vóór het bevestigen: wat betaal je en waarvoor. Dezelfde
// berekening als bij het indienen, zodat die twee nooit verschillen.
// Hier wordt ook een kortingscode gecontroleerd, dus krap: tien per minuut.
// De derde parameter geeft elke route een eigen teller; zonder die deelden
// overzicht en inzending er één, en maakte het overzicht de inzendingen op.
Route::post('inschrijven/{school:slug}/overzicht', [PublicEnrollmentController::class, 'preview'])
    ->missing($oudAdres)
    ->middleware('throttle:10,1,enroll-preview')
    ->name('enroll.preview');
