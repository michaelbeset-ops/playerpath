<?php

namespace App\Support\Trainings;

use App\Enums\Feature;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Models\User;
use App\Support\Features\Features;
use Illuminate\Support\Collection;

/**
 * "Je training is bijna klaar — vul de rapporten in."
 *
 * Het moment waarop een trainer een rapport invult is het moment dat hij nog
 * op het veld staat. Een uur later thuis weet hij niet meer wat hij zag, en
 * een dag later vult hij het niet meer in. Vandaar dit blok: het verschijnt
 * tien minuten voor het einde en blijft vijf uur staan.
 *
 * Vijf regels die dit bruikbaar houden:
 *
 * 1. **Het is een blok, geen pop-up.** Iets dat over je scherm springt terwijl
 *    je nog aan het afvinken bent, klik je weg zonder te lezen.
 * 2. **Het verdwijnt vanzelf** zodra alle spelers van die training een rapport
 *    hebben, of zodra het venster voorbij is. Een herinnering die blijft staan
 *    nadat je hem hebt afgehandeld, leer je negeren.
 * 3. **Het venster wordt hier berekend**, op de eindtijd van de training, en
 *    niet in de browser. Een telefoon met een verkeerde klok zou het blok
 *    anders op het verkeerde moment tonen.
 * 4. **Meerdere trainingen tegelijk kan**, met de laatst afgelopen bovenaan.
 * 5. **Afgezegde trainingen tellen niet.** Er is niets gebeurd om op te
 *    schrijven.
 *
 * ## Wie krijgt het te zien
 *
 * De trainers die aan de training gekoppeld zijn. Is er niemand gekoppeld —
 * en dat is bij veel scholen zo, want koppelen is informatief — dan krijgen
 * alle trainers en de eigenaar het. Anders doet deze herinnering bij die
 * scholen simpelweg niets, en dat is erger dan hem aan één iemand te veel
 * laten zien.
 *
 * ## Wanneer heet een rapport "gedaan"
 *
 * Als er voor die speler een rapport bestaat met `reported_on` op de dag van de
 * training. Rapporten hangen bewust niet aan een training: een trainer schrijft
 * over een speler, niet over een sessie. De datum is het enige eerlijke
 * verband, en het klopt in het geval waar het om gaat — de trainer die na
 * afloop op het veld zijn rapporten invult.
 */
class ReportPrompts
{
    /** Hoe lang vóór de eindtijd het blok verschijnt. */
    public const MINUTEN_VOOR_EINDE = 10;

    /** Hoe lang na de eindtijd het blijft staan. */
    public const UREN_NA_EINDE = 5;

    public function __construct(protected Features $features) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        if (! $this->features->enabled(Feature::Ontwikkeling, $user->school)) {
            return [];
        }

        if (! $user->isTrainer() && ! $user->isEigenaar()) {
            return [];
        }

        $trainingen = $this->inHetVenster($user);

        return $trainingen
            ->map(fn (Training $training) => $this->beschrijf($training))
            // Alles gedaan: dan is er niets meer te herinneren.
            ->filter(fn (array $rij) => $rij['open'] > 0)
            ->values()
            ->all();
    }

    /**
     * De trainingen waarvan de eindtijd binnen het venster valt.
     *
     * @return Collection<int, Training>
     */
    protected function inHetVenster(User $user): Collection
    {
        $vanaf = now()->subHours(self::UREN_NA_EINDE);
        $tot = now()->addMinutes(self::MINUTEN_VOOR_EINDE);

        return Training::query()
            ->whereNull('cancelled_at')
            ->whereBetween('ends_at', [$vanaf, $tot])
            ->with(['group', 'trainers'])
            ->orderByDesc('ends_at')
            ->get()
            ->filter(fn (Training $training) => $this->isVanHem($training, $user))
            ->values();
    }

    /**
     * Is deze training van deze gebruiker?
     *
     * Zonder gekoppelde trainers is hij van iedereen die training geeft; zie
     * de uitleg bovenaan.
     */
    protected function isVanHem(Training $training, User $user): bool
    {
        if ($training->trainers->isEmpty()) {
            return true;
        }

        return $training->trainers->contains('id', $user->id);
    }

    /** @return array<string, mixed> */
    protected function beschrijf(Training $training): array
    {
        $spelers = $training->expectedPlayers();

        $gedaan = Report::query()
            ->whereIn('player_id', $spelers->pluck('id'))
            ->whereDate('reported_on', $training->starts_at->toDateString())
            ->pluck('player_id')
            ->unique()
            ->flip();

        $rijen = $spelers->map(fn (Player $speler) => [
            'id' => $speler->id,
            'name' => $speler->full_name,
            'photo' => $speler->photo_url,
            'done' => $gedaan->has($speler->id),
        ]);

        return [
            'id' => $training->id,
            'group' => $training->label(),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
            'date' => $training->starts_at->translatedFormat('l j F'),
            'location' => $training->location,
            'ended' => $training->ends_at->isPast(),
            'players' => $rijen->values()->all(),
            'done' => $rijen->where('done', true)->count(),
            'open' => $rijen->where('done', false)->count(),
            'total' => $rijen->count(),
        ];
    }
}
