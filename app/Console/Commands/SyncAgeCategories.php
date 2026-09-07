<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\School;
use App\Support\Rating\RatingEngine;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

/**
 * De leeftijdscategorie van elke speler vaststellen, en overgangen vastleggen.
 *
 * Draait elke nacht. Bijna altijd verandert er niets; rond de jaarwisseling
 * gaat een deel van de spelers een jaargang omhoog, en dan wordt hier hun oude
 * kaart als seizoenskaart bewaard voordat de categorie wisselt.
 *
 * Idempotent: een speler die al in de juiste categorie staat wordt overgeslagen.
 */
class SyncAgeCategories extends Command
{
    protected $signature = 'players:categories {--dry-run : Alleen tonen wie er van categorie wisselt}';

    protected $description = 'Stelt de leeftijdscategorie van elke speler vast en bewaart de kaart bij een overgang';

    public function handle(Tenancy $tenancy, RatingEngine $engine): int
    {
        $droog = (bool) $this->option('dry-run');
        $overgangen = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            $tenancy->forSchool($school, function () use ($engine, $droog, &$overgangen) {
                foreach (Player::query()->whereNotNull('date_of_birth')->cursor() as $speler) {
                    $nieuw = $engine->categoryFor($speler);

                    if ($speler->age_category === $nieuw) {
                        continue;
                    }

                    $this->line("  {$speler->full_name}: ".($speler->age_category ?? 'nog niets').' → '.$nieuw.($droog ? ' (proefdraai)' : ''));

                    if ($droog) {
                        continue;
                    }

                    if ($engine->syncCategory($speler)) {
                        $overgangen++;
                    }
                }
            });
        }

        $this->info($droog ? 'Proefdraai klaar; er is niets gewijzigd.' : "Klaar. {$overgangen} overgang(en) vastgelegd.");

        return self::SUCCESS;
    }
}
