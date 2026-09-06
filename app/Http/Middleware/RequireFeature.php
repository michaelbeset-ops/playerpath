<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Support\Features\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sluit een route af als de functie voor deze school uitstaat.
 *
 * Dit is de reden dat een uitgezette feature écht dicht zit en niet alleen uit
 * het menu is gehaald. Het menu verbergen is cosmetica; wie de URL intypt of
 * een oude bladwijzer heeft, moet hier stuklopen.
 *
 * Een 404 en geen 403: een functie die voor jouw school niet bestaat, bestaat
 * niet. Een 403 zou verklappen dat er meer in het product zit dan je hebt.
 */
class RequireFeature
{
    public function __construct(protected Features $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $onderdeel = Feature::tryFrom($feature);

        // Een typefout in een routebestand mag nooit stilzwijgend "toegang"
        // betekenen; dan is dichtdoen het veilige antwoord.
        abort_if($onderdeel === null, 404);

        abort_if($this->features->disabled($onderdeel), 404);

        return $next($request);
    }
}
