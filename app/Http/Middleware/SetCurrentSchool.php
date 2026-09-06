<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet de actieve school op basis van de ingelogde gebruiker.
 *
 * De school komt nadrukkelijk NIET uit de URL, een subdomein of een
 * formulierveld — alleen uit het account waarmee je bent ingelogd. Daarmee
 * valt er niets te knoeien.
 */
class SetCurrentSchool
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            // Niet ingelogd: geen school. De scope levert dan niets op.
            $this->tenancy->forget();

            return $next($request);
        }

        // Een platformbeheerder hoort bij geen enkele school. Hij krijgt hier
        // dus ook geen school, en de scope blijft voor hem fail-closed: in de
        // gewone app ziet hij niets. Over scholen heen kijken kan alleen in
        // /beheer, en één school bekijken alleen via impersonatie.
        if ($user->isPlatformbeheerder() && $user->school_id === null) {
            $this->tenancy->forget();

            return $next($request);
        }

        // Een gedeactiveerd account komt nergens meer bij. Hier en niet in de
        // inlogflow, want een sessie die al liep moet net zo goed stoppen.
        abort_if(
            ! $user->isActief(),
            403,
            'Dit account is gedeactiveerd. Neem contact op met je schoolbeheerder.'
        );

        abort_if(
            $user->school_id === null,
            403,
            'Je account is niet aan een school gekoppeld. Neem contact op met je schoolbeheerder.'
        );

        $school = $user->school;

        abort_if(
            $school === null || ! $school->is_active,
            403,
            'Deze school is niet actief. Neem contact op met je schoolbeheerder.'
        );

        $this->tenancy->set($school);

        return $next($request);
    }
}
