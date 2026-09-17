<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentSchool;
use App\Http\Middleware\ShareBranding;
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
        // 'feature:kalender' op een route sluit hem af als de school die
        // functie uit heeft staan. Zie App\Enums\Feature.
        $middleware->alias(['feature' => RequireFeature::class]);

        // Op elk antwoord, ook op de webhook en de publieke pagina's.
        $middleware->append(SecurityHeaders::class);

        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
        ]);

        // De webhook komt van Mollie, niet uit een browser met een sessie.
        $middleware->validateCsrfTokens(except: ['webhooks/mollie']);

        // SetCurrentSchool staat bewust vóór Inertia: alles wat daarna draait
        // (inclusief gedeelde props) werkt al binnen de juiste school.
        $middleware->web(append: [
            SetCurrentSchool::class,
            ShareBranding::class,
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
            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('status', 'Je sessie was verlopen. Probeer het nog een keer.');
            }

            // Een Inertia-verzoek (een klik of een formulier binnen de app) krijgt
            // anders een kale HTML-foutpagina in een modaal venster. Voor fouten
            // waar je gewoon verder kunt, sturen we terug met een melding; een
            // gewone paginaweergave houdt de Nederlandse foutpagina.
            // Een 500 laten we tijdens ontwikkelen staan: dan wil je de fout zien.
            $foutpagina = in_array($status, [500, 503], true) && app()->hasDebugModeEnabled();

            if ($request->header('X-Inertia') && ! $foutpagina) {
                $melding = match ($status) {
                    403 => 'Dat mag je met dit account niet doen.',
                    404 => 'Dat bestaat niet (meer). Misschien is het net verwijderd.',
                    413 => 'Het bestand is te groot. Kies een kleiner bestand.',
                    429 => 'Even rustig aan: probeer het over een minuut nog een keer.',
                    500, 503 => 'Er ging iets mis. Probeer het nog een keer.',
                    default => null,
                };

                if ($melding !== null) {
                    return back()->with('status', $melding);
                }
            }

            return $response;
        });
    })->create();
