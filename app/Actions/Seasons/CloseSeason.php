<?php

namespace App\Actions\Seasons;

use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\School;
use App\Notifications\SeizoenAfgesloten;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\SchoolSeason;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Een seizoen afsluiten: de kaart van elke speler bewaren, de XP opnieuw
 * laten beginnen, en de ouders en spelers hun eindkaart laten weten.
 *
 * Drie dingen die je niet moet omdraaien:
 *
 * 1. **De rating blijft staan.** Die zegt hoe goed een speler is; alleen de
 *    punten (inzet) beginnen opnieuw. Anders lijkt een keeper na de
 *    zomervakantie ineens niets meer te kunnen.
 * 2. **De XP-boekingen blijven bestaan.** De som telt vanaf `xp_from`; de
 *    oude boekingen zijn de historie in de tijdlijn en op de seizoenskaart.
 * 3. **Idempotent.** Een seizoen dat al dicht is (closed_at) wordt niet nog
 *    eens afgesloten, en een bestaande seizoenskaart wordt bijgewerkt in
 *    plaats van verdubbeld.
 */
class CloseSeason
{
    public function __construct(
        protected Tenancy $tenancy,
        protected RatingEngine $engine,
    ) {}

    /** @return int het aantal bewaarde kaarten */
    public function handle(School $school, bool $notify = true): int
    {
        $seizoen = SchoolSeason::for($school);

        if (! $seizoen->isSet()) {
            return 0;
        }

        return $this->tenancy->forSchool($school, function () use ($school, $seizoen, $notify) {
            $bewaard = 0;
            $spelers = [];

            DB::transaction(function () use ($school, $seizoen, &$bewaard, &$spelers) {
                foreach (Player::query()->active()->cursor() as $speler) {
                    $settings = $this->engine->settingsFor($speler);

                    // Alleen een kaart die iets voorstelt: zonder rapport en
                    // zonder punten valt er niets te bewaren.
                    if ($speler->overall_rating === null && (int) $speler->xp === 0) {
                        continue;
                    }

                    PlayerCardSeason::query()->updateOrCreate(
                        [
                            'player_id' => $speler->id,
                            'season' => $seizoen->name,
                            'age_category' => $speler->age_category ?? $this->engine->categoryFor($speler),
                        ],
                        [
                            'overall_rating' => $speler->overall_rating,
                            'category_ratings' => $speler->category_ratings,
                            'xp' => (int) $speler->xp,
                            'level' => $this->engine->level((int) $speler->xp, $speler->overall_rating, $settings)['key'],
                            'report_count' => $speler->reports()->count(),
                        ],
                    );

                    $bewaard++;
                    $spelers[] = $speler;
                }

                // Dicht, en de punten tellen vanaf morgen opnieuw, ook als er
                // nog geen nieuw seizoen is ingesteld.
                SchoolSeason::save($school, [
                    'closed_at' => now()->toIso8601String(),
                    'xp_from' => now()->addDay()->toDateString(),
                ]);

                foreach ($spelers as $speler) {
                    $this->engine->recalculate($speler->fresh());
                }
            });

            if ($notify) {
                foreach ($spelers as $speler) {
                    $ontvangers = $speler->guardians->all();

                    if ($speler->user !== null) {
                        $ontvangers[] = $speler->user;
                    }

                    foreach ($ontvangers as $ontvanger) {
                        $ontvanger->notify(new SeizoenAfgesloten($speler, (string) $seizoen->name));
                    }
                }
            }

            return $bewaard;
        });
    }
}
