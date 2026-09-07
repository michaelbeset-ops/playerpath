<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kopteksten die de browser vertellen wat hij níét moet doen.
 *
 * Geen van deze regels vervangt de beveiliging in de app zelf; ze vangen de
 * gevallen op waarin er tóch iets misgaat. Daarom zitten ze hier en niet in
 * een configuratiebestand van de webserver: zo staan ze in de code, gaan ze
 * mee naar elke omgeving, en staat er een test op.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // De app mag niet in een iframe van iemand anders staan: dat is hoe
        // clickjacking werkt — een onzichtbare knop over die van jou heen.
        //
        // Eén uitzondering: de aanmeldpagina. Die is bedoeld om op de eigen
        // website van de school te zetten, en daar staat niets achter een
        // sessie — geen inlog, geen gegevens van anderen, alleen een formulier
        // dat een inschrijving oplevert. Clickjacking valt daar niets mee te
        // winnen, en zonder deze uitzondering blijft het iframe leeg.
        if (! $request->routeIs('enroll.*')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // Geen gokwerk over bestandstypen. Een geüpload "logo" dat stiekem
        // JavaScript is, wordt dan niet alsnog uitgevoerd.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Bij het doorklikken naar buiten alleen het domein meesturen, niet het
        // volledige adres: een deel-link van een spelerskaart hoort niet in de
        // logs van een vreemde site te belanden.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // We vragen nergens om camera, microfoon of locatie.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // HSTS alleen op productie én alleen over https. Lokaal zou dit je
        // browser dwingen om playerpath.test over https te openen, en dat
        // krijg je er maanden niet meer uit.
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
