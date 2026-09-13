<?php

namespace App\Console\Commands;

use App\Actions\Seasons\CloseSeason;
use App\Models\School;
use App\Support\Rating\SchoolSeason;
use Illuminate\Console\Command;

/**
 * Seizoenen afsluiten waarvan de einddatum voorbij is.
 *
 * Draait elke nacht. Bijna altijd gebeurt er niets; op de dag na de
 * einddatum worden de kaarten bewaard, beginnen de punten opnieuw en horen
 * ouders en spelers dat hun eindkaart klaarstaat.
 */
class CloseSeasons extends Command
{
    protected $signature = 'seasons:close';

    protected $description = 'Sluit seizoenen af waarvan de einddatum voorbij is: kaart bewaren, punten opnieuw';

    public function handle(CloseSeason $sluit): int
    {
        $aantal = 0;

        foreach (School::where('is_active', true)->cursor() as $school) {
            if (! SchoolSeason::for($school)->hasEnded()) {
                continue;
            }

            $kaarten = $sluit->handle($school);
            $this->line("  {$school->name}: seizoen afgesloten, {$kaarten} kaarten bewaard");
            $aantal++;
        }

        $this->info($aantal === 0 ? 'Geen seizoen om af te sluiten.' : "{$aantal} seizoen(en) afgesloten.");

        return self::SUCCESS;
    }
}
