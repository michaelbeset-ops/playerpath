<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stuurt een kind-account (zie EnsurePlayerAccount) weg van de instellingen.
 *
 * Zo'n account heeft geen mailbox en geen wachtwoord dat iemand kent. Een
 * kind dat daar per ongeluk zijn e-mailadres of wachtwoord wijzigt, of zijn
 * account verwijdert, sluit zichzelf buiten.
 */
class RedirectKindAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isKindAccount()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
