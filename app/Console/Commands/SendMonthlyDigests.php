<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\Player;
use App\Models\School;
use App\Notifications\MaandelijkseUpdate;
use App\Support\Features\Features;
use App\Support\Progress\MonthlyDigest;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * De maandelijkse update aan ouders en spelers.
 *
 * Draait op de eerste van de maand. Bewust één bericht per speler en niet per
 * ouder: een ouder met twee kinderen wil twee verschillende verhalen, niet één
 * samengevoegd bericht waarin niet meer staat wie wat deed.
 */
class SendMonthlyDigests extends Command
{
    protected $signature = 'players:digest {--dry-run : Alleen tonen wat er zou gaan}';

    protected $description = 'Stuurt ouders en spelers de maandelijkse samenvatting van hun ontwikkeling';

    public function handle(Tenancy $tenancy, MonthlyDigest $digest): int
    {
        $droog = (bool) $this->option('dry-run');
        $verstuurd = 0;
        $overgeslagen = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            // Staat de ontwikkelingslaag uit, dan gaat er ook geen samenvatting
            // over uit. Alleen het scherm verbergen zou betekenen dat ouders van
            // een school zonder rapporten toch een maandmail krijgen.
            if (! Features::enabledFor($school, Feature::Ontwikkeling)) {
                continue;
            }

            $tenancy->forSchool($school, function () use ($digest, $droog, &$verstuurd, &$overgeslagen) {
                $spelers = Player::query()
                    ->where('is_active', true)
                    ->with(['guardians', 'user', 'groups'])
                    ->get();

                foreach ($spelers as $speler) {
                    $inhoud = $digest->for($speler);

                    if ($inhoud === null) {
                        $overgeslagen++;

                        continue;
                    }

                    $ontvangers = $speler->guardians->all();

                    if ($speler->user !== null) {
                        $ontvangers[] = $speler->user;
                    }

                    if ($ontvangers === []) {
                        $overgeslagen++;

                        continue;
                    }

                    $this->line("  {$speler->full_name} — {$inhoud['reports']} rapport(en), {$inhoud['attended']}x aanwezig");

                    if ($droog) {
                        continue;
                    }

                    Notification::send($ontvangers, new MaandelijkseUpdate($speler, $inhoud));
                    $verstuurd++;
                }
            });
        }

        $this->info($droog
            ? "Proefdraai: er is niets verstuurd. {$overgeslagen} speler(s) zouden worden overgeslagen."
            : "Klaar. {$verstuurd} update(s) verstuurd, {$overgeslagen} overgeslagen omdat er niets te melden was.");

        return self::SUCCESS;
    }
}
