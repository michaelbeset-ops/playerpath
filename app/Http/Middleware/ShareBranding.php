<?php

namespace App\Http\Middleware;

use App\Support\Branding\Branding;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * De huisstijl van de school klaarzetten voor dit verzoek.
 *
 * Bewust ook naar Blade en niet alleen naar Inertia: de kleuren komen als
 * `<style>` in de `<head>` te staan, vóór het eerste beeld. Zou Vue ze pas
 * zetten, dan ziet iedere bezoeker eerst een flits PlayerPath-groen voordat
 * zijn eigen kleur verschijnt.
 *
 * Het resultaat gaat in de container, zodat HandleInertiaRequests er niet
 * nog een keer de database voor hoeft te raadplegen.
 */
class ShareBranding
{
    public function __construct(protected Branding $branding) {}

    public function handle(Request $request, Closure $next): Response
    {
        $school = $this->branding->forRequest($request);
        $huisstijl = $this->branding->describe($school);

        app()->instance('branding', $huisstijl);
        View::share('branding', $huisstijl);

        return $next($request);
    }
}
