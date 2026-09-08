<?php

namespace App\Actions\Onboarding;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Support\Onboarding\OnboardingState;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * De voorbeelddata er in één keer uit.
 *
 * Dit is de tegenhanger die de voorbeelddata verantwoord maakt: zonder deze
 * knop staat er over een half jaar nog een verzonnen kind in het ledenbestand
 * van een echte school, en dat is erger dan een leeg beginscherm.
 *
 * Drie eigenschappen:
 *
 * 1. **Alleen wat als voorbeeld is neergezet.** De filter is `is_demo`, en die
 *    vlag staat in geen enkel `$fillable` — hij kan dus niet per ongeluk op een
 *    echte speler terechtkomen via een formulier.
 * 2. **De volgorde is van klein naar groot.** Eerst de rapporten en de
 *    trainingen, dan de spelers en de groep, dan de rest. De
 *    databasesleutels zouden het meeste zelf opruimen, maar hierdoor hangt het
 *    resultaat niet af van hoe een sleutel toevallig is gezet.
 * 3. **Het is één keer.** Erna staat `demo_removed_at` en komt de knop niet
 *    meer terug; opnieuw neerzetten bestaat niet, want dan zou je je eigen
 *    school vervuilen met kinderen die je net hebt weggehaald.
 */
class RemoveDemoData
{
    public function __construct(protected Tenancy $tenancy) {}

    /** @return array<string, int> wat er is opgeruimd, voor de melding erna */
    public function handle(School $school): array
    {
        $this->tenancy->set($school);

        return DB::transaction(function () {
            $spelers = Player::where('is_demo', true)->pluck('id');

            $geteld = [
                'reports' => Report::where('is_demo', true)->count(),
                'players' => $spelers->count(),
                'trainings' => Training::where('is_demo', true)->count(),
                'groups' => Group::where('is_demo', true)->count(),
                'products' => Product::where('is_demo', true)->count(),
                'announcements' => Announcement::where('is_demo', true)->count(),
                'locations' => Location::where('is_demo', true)->count(),
            ];

            Report::where('is_demo', true)->delete();
            Training::where('is_demo', true)->delete();

            // Per stuk en niet in één query: een speler heeft koppelingen aan
            // groepen, XP-boekingen en aanwezigheid, en die gaan via de
            // modelgebeurtenissen mee.
            Player::where('is_demo', true)->get()->each->delete();

            Group::where('is_demo', true)->get()->each->delete();
            Product::where('is_demo', true)->get()->each->delete();
            Announcement::where('is_demo', true)->delete();
            Location::where('is_demo', true)->delete();

            return $geteld;
        });
    }

    public function finish(School $school): void
    {
        OnboardingState::mark($school, 'demo_removed_at');
    }
}
