<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Closure;

/**
 * De school zetten voor werk dat in de achtergrond draait.
 *
 * Dit is voor de wachtrij wat `SetCurrentSchool` voor een webverzoek is. In een
 * queue-worker is niemand ingelogd, dus de terugval in `AppServiceProvider`
 * (`auth()->user()?->school`) levert niets op en staat de global scope
 * fail-closed dicht. Dat is voor de veiligheid precies goed en voor de inhoud
 * precies fout: een `toMail()` die iets opzoekt krijgt geen foutmelding maar
 * een leeg antwoord, en dus een mail waar de helft uit weg is — een
 * inschrijfbevestiging zonder betaalknop, een uitnodiging die "je kind" zegt in
 * plaats van de naam.
 *
 * Twee dingen die je niet moet omdraaien:
 *
 * 1. **De school komt van de ontvanger, niet uit de payload.** Precies dezelfde
 *    regel als bij een webverzoek: nooit uit invoer. `NotificationSender` roept
 *    `middleware()` per ontvanger aan, dus dat komt uit.
 * 2. **Alleen het id reist mee.** Een heel School-model in de payload is groot
 *    en veroudert; hier wordt hij opgehaald op het moment dat het werk draait.
 *    Bestaat de school niet meer, dan blijft de scope dicht — dan hoort er ook
 *    geen mail meer uit te gaan.
 */
class WithSchool
{
    public function __construct(public ?int $schoolId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $school = $this->schoolId === null ? null : School::find($this->schoolId);

        if ($school === null) {
            return $next($job);
        }

        return app(Tenancy::class)->forSchool($school, fn () => $next($job));
    }
}
