<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\Player;
use App\Models\School;
use App\Notifications\Verjaardag;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * De verjaardagsfelicitatie.
 *
 * Draait elke ochtend. Vier dingen die je niet moet weghalen:
 *
 * 1. **De school zet hem zelf aan.** Standaard staat hij uit: het is een
 *    berichtje met de naam van de school eronder, en dat kiest zij.
 * 2. **Idempotent via `players.greeted_on`.** Twee keer draaien levert geen
 *    tweede felicitatie op — precies het soort fout dat je klanten opmerken.
 * 3. **Op dag en maand, niet op datum.** Het jaar in `date_of_birth` is het
 *    geboortejaar.
 * 4. **Alleen actieve spelers.** Iemand die vorig seizoen is gestopt krijgt
 *    geen felicitatie van een school waar hij niet meer komt.
 *
 * De felicitatie gaat naar de speler zelf als die een eigen inlog heeft, en
 * anders naar de ouders. Allebei zou betekenen dat een kind van acht en zijn
 * moeder allebei "gefeliciteerd, jij bent jarig" krijgen.
 */
class SendBirthdayGreetings extends Command
{
    protected $signature = 'players:birthday {--dry-run : Alleen tonen wie er aan de beurt is}';

    protected $description = 'Feliciteert spelers die vandaag jarig zijn';

    public function handle(Tenancy $tenancy): int
    {
        $droog = (bool) $this->option('dry-run');
        $vandaag = now()->startOfDay();
        $verstuurd = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            if (! $school->birthday_greeting) {
                continue;
            }

            // Een felicitatie is een mededeling van de school; staat die functie
            // uit, dan verstuurt de school niets.
            if (! Features::enabledFor($school, Feature::Mededelingen)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($school, $vandaag, $droog, &$verstuurd) {
                $jarig = Player::query()
                    ->where('is_active', true)
                    ->whereNotNull('date_of_birth')
                    ->whereMonth('date_of_birth', $vandaag->month)
                    ->whereDay('date_of_birth', $vandaag->day)
                    // Vandaag al gefeliciteerd? Dan niet nog een keer.
                    ->where(fn ($q) => $q->whereNull('greeted_on')->orWhereDate('greeted_on', '<', $vandaag->toDateString()))
                    ->with(['guardians', 'user'])
                    ->get();

                foreach ($jarig as $speler) {
                    $leeftijd = $vandaag->year - $speler->date_of_birth->year;

                    $ontvangers = $speler->user !== null ? [$speler->user] : $speler->guardians->all();

                    if ($ontvangers === []) {
                        $this->line("  {$speler->full_name} is jarig, maar er is niemand om te bereiken.");

                        continue;
                    }

                    $this->line("  {$speler->full_name} wordt {$leeftijd}".($droog ? ' (proefdraai)' : ''));

                    if ($droog) {
                        continue;
                    }

                    Notification::send($ontvangers, new Verjaardag($speler, $leeftijd, $school->birthday_message));

                    $speler->forceFill(['greeted_on' => $vandaag->toDateString()])->save();
                    $verstuurd++;
                }
            });
        }

        $this->info($droog
            ? 'Proefdraai klaar; er is niets verstuurd.'
            : "Klaar. {$verstuurd} felicitatie(s) verstuurd.");

        return self::SUCCESS;
    }
}
