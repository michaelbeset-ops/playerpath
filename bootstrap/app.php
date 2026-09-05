<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentSchool;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Achter een tunnel of loadbalancer (Cloudflare, Forge/nginx) komt het
        // verzoek binnen op 127.0.0.1 over http, terwijl de bezoeker https
        // gebruikt. Zonder deze regel bouwt Laravel http-URL's en blokkeert de
        // browser ze als mixed content: Ziggy-routes, formulieren en de
        // deel-link lopen dan stuk. We vertrouwen alleen loopback, want alleen
        // een proces op deze machine mag zeggen wat het schema was.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);

        // SetCurrentSchool staat bewust vóór Inertia: alles wat daarna draait
        // (inclusief gedeelde props) werkt al binnen de juiste school.
        $middleware->web(append: [
            SetCurrentSchool::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Een verlopen sessie (419) is geen fout van de gebruiker. In plaats
        // van een kale foutpagina sturen we hem terug met een duidelijke
        // melding; een trainer laat de aanwezigheidspagina makkelijk een uur
        // openstaan.
        $exceptions->respond(function ($response, $exception, $request) {
            if ($response->getStatusCode() === 419) {
                return back()->with('status', 'Je sessie was verlopen. Probeer het nog een keer.');
            }

            return $response;
        });
    })->create();
