<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Platform\ImpersonationController;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * De poort naar de beheeromgeving van het platform.
 *
 * Dit is de enige plek in de hele app die de school-scope over scholen heen
 * openzet, en het gebeurt hier pas ná de rolcontrole. Zolang die twee dingen
 * bij elkaar in één middleware staan, kan er geen route bestaan die het één
 * wel doet en het ander niet.
 *
 * Een gewone gebruiker die dit adres probeert krijgt een 404 en geen 403: dat
 * de beheeromgeving bestaat is niets wat een schooleigenaar hoeft te weten.
 */
class EnterPlatform
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Eerst de impersonatie, dán de rol. Andersom zou deze controle nooit
        // afgaan: tijdens het bekijken ben je de schoolgebruiker en val je al
        // op de rolcontrole af - met een 404 die niets uitlegt. Nu krijgt de
        // beheerder die per ongeluk terugklikt een melding waar hij iets aan
        // heeft, en blijft de scope net zo goed dicht.
        abort_if(
            $request->session()->has(ImpersonationController::SESSIE),
            403,
            'Je bekijkt op dit moment een school. Ga eerst terug naar het beheer.'
        );

        abort_unless($user !== null && $user->isPlatformbeheerder(), 404);

        $this->tenancy->enterPlatform();

        return $next($request);
    }

    /**
     * Na afloop de scope weer dichtzetten.
     *
     * In een gewone webrequest maakt dit niets uit - die krijgt toch een verse
     * container. Maar in een langlevend proces (tests, Octane) zou de stand
     * blijven hangen, en dan draait het volgende stuk werk met de scope open.
     * Dat is precies het soort lek dat je pas ontdekt als er data van de
     * verkeerde school in beeld staat.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->tenancy->leavePlatform();
    }
}
