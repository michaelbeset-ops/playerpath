<?php

namespace App\Support\Dashboard;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Support\Availability\TrainerAvailability;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Rating\RatingSettings;

/**
 * Wat er nú actie vraagt, in één lijst.
 *
 * Dit is het antwoord op de tweede vraag die een dashboard hoort te
 * beantwoorden: "wat moet ik doen?". Het staat daarom bovenaan, boven de
 * widgets, en is geen widget: je kunt het niet verplaatsen. Wegklikken kan wel,
 * maar alleen tot er iets verandert - zie signature() hieronder.
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
 *    betaalsignalen - daar kan hij niets mee.
 */
class AttentionItems
{
    /** Na hoeveel dagen een openstaande rekening om actie vraagt. */
    public const OPENSTAAND_NA_DAGEN = 14;

    /** Na hoeveel dagen zonder rapport een speler aandacht verdient. */
    public const RAPPORT_NA_DAGEN = 30;

    public function __construct(
        protected Features $features,
        protected TrainerAvailability $availability,
    ) {}

    /**
     * De vingerafdruk van wat er nu in het blok staat.
     *
     * Hierop wordt bepaald of een weggeklikt blok weg mag blijven. Hij bevat
     * de soorten signalen en hun tekst, dus "zeven rekeningen" wordt "acht
     * rekeningen" en dan komt het blok terug. Wegklikken betekent "dit heb ik
     * gezien", niet "waarschuw me nooit meer".
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function signature(array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        return substr(hash('sha256', collect($items)->map(fn (array $i) => $i['key'].'|'.$i['title'])->join("\n")), 0, 32);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function for(User $user): array
    {
        $items = [];

        if ($user->isEigenaar() && $this->features->enabled(Feature::Betalingen, $user->school)) {
            $items = [...$items, ...$this->betalingen()];
        }

        // Bij de inzetkaart bestaan er geen rapporten; "geen rapport gehad"
        // zou dan bij elk kind staan en niets zeggen.
        if ($this->features->enabled(Feature::Ontwikkeling, $user->school)
            && ! RatingSettings::for($user->school)->usesEffort()) {
            $items = [...$items, ...$this->rapporten($user)];
        }

        // De planning is werk van de eigenaar. Een trainer kan niets met "er
        // staat nergens een trainer bij"; die geeft zijn eigen trainingen.
        if ($user->isEigenaar()) {
            $items = [...$items, ...$this->planning()];
        }

        // Aanvragen voor trainingen die om goedkeuring vragen: de eigenaar
        // ziet ze allemaal, een trainer die van zijn eigen trainingen.
        $items = [...$items, ...$this->aanvragen($user)];

        return $items;
    }

    /**
     * Losse aanmeldingen die op een ja of nee wachten.
     *
     * @return list<array<string, mixed>>
     */
    protected function aanvragen(User $user): array
    {
        $query = TrainingEnrollment::query()
            ->requested()
            ->whereHas('training', fn ($q) => $q->where('starts_at', '>=', now())
                ->when(! $user->isEigenaar(), fn ($t) => $t->forTrainer($user)))
            ->with(['training', 'player'])
            ->orderBy('created_at');

        $aantal = $query->count();

        if ($aantal === 0) {
            return [];
        }

        $eerste = $query->first();
        $training = $eerste->training;

        return [[
            'key' => 'training_requests',
            'tone' => 'warning',
            'icon' => 'trainings',
            'title' => $aantal === 1
                ? "{$eerste->player?->first_name} wil meedoen aan {$training->label()}"
                : "{$aantal} aanvragen voor trainingen wachten op je antwoord",
            'body' => ucfirst($training->starts_at->translatedFormat('l j F')).', '.$training->starts_at->format('H:i').' · goedkeuren of afwijzen.',
            'href' => '/trainings/'.$training->id,
            'action' => 'Bekijk de aanvraag',
        ]];
    }

    /**
     * Waar de planning en de beschikbaarheid uit elkaar lopen.
     *
     * Twee losse signalen, want het zijn twee verschillende gesprekken: bij het
     * ene bel je een trainer, bij het andere koppel je er een. Ze samenvatten
     * als "vier trainingen hebben een probleem" laat je alsnog zoeken welk.
     *
     * @return list<array<string, mixed>>
     */
    protected function planning(): array
    {
        $conflicten = $this->availability->conflicts();
        $items = [];

        $onbeschikbaar = count($conflicten['unavailable']);

        if ($onbeschikbaar > 0) {
            $eerste = $conflicten['unavailable'][0];

            $items[] = [
                'key' => 'unavailable_trainers',
                'tone' => 'warning',
                'icon' => 'trainer',
                'title' => $onbeschikbaar === 1
                    ? $eerste['trainer'].' staat ingepland op een moment dat hij niet kan'
                    : $onbeschikbaar.' trainingen staan gepland met een trainer die dan niet kan',
                'body' => ucfirst($eerste['date']).', '.$eerste['time'].' · '.$eerste['group'].'.',
                'href' => '/personeel/beschikbaarheid',
                'action' => 'Naar de planning',
            ];
        }

        $zonder = count($conflicten['unstaffed']);

        if ($zonder > 0) {
            $eerste = $conflicten['unstaffed'][0];

            $items[] = [
                'key' => 'unstaffed_trainings',
                'tone' => 'neutral',
                'icon' => 'trainer',
                'title' => $zonder === 1
                    ? 'Bij één training staat nog geen trainer'
                    : "Bij {$zonder} trainingen staat nog geen trainer",
                'body' => ucfirst($eerste['date']).', '.$eerste['time'].' · '.$eerste['group'].'.',
                'href' => '/trainings/'.$eerste['id'].'/edit',
                'action' => 'Trainer koppelen',
            ];
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
    protected function rapporten(User $user): array
    {
        $items = [];

        $grens = now()->subDays(self::RAPPORT_NA_DAGEN);

        // Een trainer krijgt alleen zijn eigen spelers: over de rest kan hij
        // niets doen, en hij mag ze ook niet openen. Zie TrainerScope.
        $stil = Player::active()
            ->visibleTo($user)
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

        // Wie er verder nog niets invulde gaat een trainer niet aan.
        if (! $user->isEigenaar()) {
            return $items;
        }

        // Trainers die deze week nog niets invulden. De eigenaar telt mee als
        // trainer: bij een kleine school geeft hij zelf ook training. Alleen
        // wie aan een training gekoppeld is: een eigenaar die niet traint of
        // een trainer zonder rooster hoeft niets in te vullen.
        $trainers = User::ofCurrentSchool()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['trainer', 'eigenaar']))
            ->whereHas('trainings')
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
                // Bij één trainer staat zijn naam al in de kop; dan zegt de
                // tekst waarom het ertoe doet in plaats van de naam te herhalen.
                'body' => $trainers->count() === 1
                    ? 'Een rapport vlak na de training houdt de spelerskaarten actueel, en dat is wat ouders zien.'
                    : $trainers->take(4)->pluck('name')->join(', ', ' en ').'.',
                'href' => '/reports',
                'action' => 'Naar rapporten',
            ];
        }

        return $items;
    }
}
