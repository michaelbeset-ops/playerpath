<?php

namespace App\Support\Dashboard;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\User;
use App\Support\Features\Features;
use App\Support\Money\Money;

/**
 * Wat er nú actie vraagt, in één lijst.
 *
 * Dit is het antwoord op de tweede vraag die een dashboard hoort te
 * beantwoorden: "wat moet ik doen?". Het staat daarom bovenaan en is niet weg
 * te klikken.
 *
 * Vier regels die deze lijst bruikbaar houden:
 *
 * 1. **Alleen wat je vandaag kunt oplossen.** Geen cijfers ter informatie; elk
 *    item heeft een knop die je naar de plek brengt waar je het afhandelt.
 * 2. **Elk signaal staat hier en nergens anders.** Stond "spelers zonder
 *    rapport" ook nog als los blok, dan lees je hetzelfde twee keer en ga je
 *    beide negeren.
 * 3. **Niets tonen is ook een uitkomst.** Bij een lege lijst staat er één
 *    geruststellende regel; een leeg vak met een kopje leest als een fout.
 * 4. **Wat deze rol niet mag zien, staat er niet in.** Een trainer krijgt geen
 *    betaalsignalen — daar kan hij niets mee.
 */
class AttentionItems
{
    /** Na hoeveel dagen een openstaande rekening om actie vraagt. */
    public const OPENSTAAND_NA_DAGEN = 14;

    /** Na hoeveel dagen zonder rapport een speler aandacht verdient. */
    public const RAPPORT_NA_DAGEN = 30;

    public function __construct(protected Features $features) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        $items = [];

        if ($user->isEigenaar() && $this->features->enabled(Feature::Betalingen, $user->school)) {
            $items = [...$items, ...$this->betalingen()];
        }

        if ($this->features->enabled(Feature::Ontwikkeling, $user->school)) {
            $items = [...$items, ...$this->rapporten()];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    protected function betalingen(): array
    {
        $items = [];

        $mislukt = Payment::whereIn('status', [PaymentStatus::Failed->value, PaymentStatus::ChargedBack->value]);
        $aantalMislukt = (clone $mislukt)->count();

        if ($aantalMislukt > 0) {
            $items[] = [
                'key' => 'failed_payments',
                'tone' => 'danger',
                'icon' => 'payment',
                'title' => $aantalMislukt === 1
                    ? 'Eén betaling is mislukt of gestorneerd'
                    : "{$aantalMislukt} betalingen zijn mislukt of gestorneerd",
                'body' => 'Samen '.Money::format((int) (clone $mislukt)->sum('amount_cents')).'. Dit geld komt niet vanzelf binnen.',
                'href' => '/payments?tab=all&period=all',
                'action' => 'Bekijken',
            ];
        }

        $grens = now()->subDays(self::OPENSTAAND_NA_DAGEN);

        $laat = Payment::where('status', PaymentStatus::Open->value)
            ->whereDate('due_on', '<', $grens->toDateString());
        $aantalLaat = (clone $laat)->count();

        if ($aantalLaat > 0) {
            $items[] = [
                'key' => 'overdue_payments',
                'tone' => 'warning',
                'icon' => 'payment',
                'title' => $aantalLaat === 1
                    ? 'Eén rekening staat langer dan '.self::OPENSTAAND_NA_DAGEN.' dagen open'
                    : "{$aantalLaat} rekeningen staan langer dan ".self::OPENSTAAND_NA_DAGEN.' dagen open',
                'body' => 'Samen '.Money::format((int) (clone $laat)->sum('amount_cents')).'.',
                'href' => '/payments?tab=overdue&period=all',
                'action' => 'Naar openstaand',
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    protected function rapporten(): array
    {
        $items = [];

        $grens = now()->subDays(self::RAPPORT_NA_DAGEN);

        $stil = Player::active()
            ->withMax('reports', 'reported_on')
            ->get()
            ->filter(fn (Player $speler) => $speler->reports_max_reported_on === null
                || $speler->reports_max_reported_on < $grens->toDateString());

        if ($stil->isNotEmpty()) {
            $eerste = $stil->sortBy(fn (Player $s) => $s->reports_max_reported_on ?? '')->first();

            $items[] = [
                'key' => 'silent_players',
                'tone' => 'warning',
                'icon' => 'report',
                'title' => $stil->count() === 1
                    ? $eerste->full_name.' heeft al '.self::RAPPORT_NA_DAGEN.' dagen geen rapport gehad'
                    : $stil->count().' spelers hebben al '.self::RAPPORT_NA_DAGEN.' dagen geen rapport gehad',
                'body' => 'Een lege kaart is precies waarom een ouder afhaakt.',
                'href' => '/players/'.$eerste->id.'/reports/create',
                'action' => 'Rapport invullen',
            ];
        }

        // Trainers die deze week nog niets invulden. De eigenaar telt mee als
        // trainer: bij een kleine school geeft hij zelf ook training.
        $trainers = User::ofCurrentSchool()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['trainer', 'eigenaar']))
            ->whereDoesntHave('reports', fn ($q) => $q->whereDate('reported_on', '>=', now()->startOfWeek()->toDateString()))
            ->get(['id', 'name']);

        if ($trainers->isNotEmpty() && Report::query()->exists()) {
            $items[] = [
                'key' => 'quiet_trainers',
                'tone' => 'neutral',
                'icon' => 'trainer',
                'title' => $trainers->count() === 1
                    ? $trainers->first()->name.' vulde deze week nog geen rapport in'
                    : $trainers->count().' trainers vulden deze week nog geen rapport in',
                'body' => $trainers->take(4)->pluck('name')->join(', ', ' en ').'.',
                'href' => '/reports',
                'action' => 'Naar rapporten',
            ];
        }

        return $items;
    }
}
