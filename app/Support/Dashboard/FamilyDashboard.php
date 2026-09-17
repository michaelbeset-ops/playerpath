<?php

namespace App\Support\Dashboard;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\Training;
use App\Models\User;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\Rating\RatingSettings;
use App\Support\Trainings\VisibleTrainings;

/**
 * Het dashboard van een ouder.
 *
 * Een ouder komt hier voor praktische dingen: wanneer is de training, moet ik
 * nog betalen of inschrijven, is er nieuws? De spelerskaart is het mooiste wat
 * dit product maakt, maar hij beantwoordt geen van die vragen - vandaar dat hij
 * hier klein staat en met één tik groot wordt.
 *
 * Drie regels die dit scherm bruikbaar houden:
 *
 * 1. **Meerdere kinderen is het gewone geval.** Alles wat hier staat noemt bij
 *    welk kind het hoort; een lijst met trainingen zonder naam is bij twee
 *    kinderen onbruikbaar.
 * 2. **Leeg is weg.** Een blok zonder inhoud verdwijnt; een kader met "geen
 *    berichten" is ruimte die je elke dag opnieuw moet overslaan.
 * 3. **De kinderen komen uit `visiblePlayerIds()`**, dezelfde bron als de
 *    trainingen, de kaart en de betalingen. Eén plek die bepaalt van wie je
 *    kind bent.
 */
class FamilyDashboard
{
    /** Over hoeveel dagen "groei deze maand" gaat. */
    public const GROEI_DAGEN = 30;

    public function __construct(
        protected VisibleTrainings $visible,
        protected PlayerBadges $badges,
        protected Features $features,
    ) {}

    /**
     * De kinderen van deze ouder, compact.
     *
     * @param  list<int>  $spelerIds
     * @return list<array<string, mixed>>
     */
    public function children(array $spelerIds): array
    {
        // De kinderen zitten op één school; de kaartkeuze is dus één vraag.
        $spelers = Player::whereIn('id', $spelerIds)
            ->withCount('reports')
            ->orderBy('first_name')
            ->get();

        $inzet = RatingSettings::for($spelers->first()?->school)->usesEffort();

        return $spelers
            ->map(function (Player $speler) use ($inzet) {
                $level = $this->badges->level($speler);

                return [
                    'id' => $speler->id,
                    'name' => $speler->full_name,
                    'first_name' => $speler->first_name,
                    'photo' => $speler->photo_url,
                    'position' => $speler->position->label(),
                    'overall' => $inzet ? null : $speler->overall_rating,
                    'level' => $level['key'],
                    'level_label' => $level['label'],
                    'xp' => $level['xp'],
                    'xp_progress' => $level['progress'],
                    'next_level' => $level['next']['label'] ?? null,
                    'growth' => $inzet ? null : $this->groei($speler),
                    'report_count' => (int) $speler->reports_count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Hoeveel een speler de laatste maand gegroeid is.
     *
     * Uit de rapporten, met dezelfde afronding als de kaart: het verschil
     * tussen het eerste en het laatste rapport in die periode. Met één rapport
     * valt er niets te vergelijken, en dan staat er niets.
     */
    protected function groei(Player $speler): ?int
    {
        $rapporten = $speler->reports()
            ->whereDate('reported_on', '>=', now()->subDays(self::GROEI_DAGEN)->toDateString())
            ->with('scores')
            ->orderBy('reported_on')
            ->orderBy('id')
            ->get();

        if ($rapporten->count() < 2) {
            return null;
        }

        $cijfer = fn (Report $rapport) => $rapport->scores->isEmpty()
            ? null
            : CalculatePlayerCard::afronden($rapport->scores->avg('score') * 10);

        $eerste = $cijfer($rapporten->first());
        $laatste = $cijfer($rapporten->last());

        return $eerste === null || $laatste === null ? null : $laatste - $eerste;
    }

    /**
     * De eerstvolgende trainingen van alle kinderen, chronologisch.
     *
     * Bij welk kind een training hoort staat erbij: met twee kinderen in
     * verschillende groepen is een rij tijdstippen zonder naam onbruikbaar.
     * Drie stuks: het scherm opent hiermee, en meer dan drie duwt de rest van
     * het dashboard van het scherm af. "Bekijk meer" gaat naar /trainings, dat
     * via VisibleTrainings dezelfde grens houdt: alleen de eigen kinderen.
     *
     * @param  list<int>  $spelerIds
     * @return list<array<string, mixed>>
     */
    public function upcomingTrainings(User $user, array $spelerIds, int $limiet = 3): array
    {
        $namen = Player::whereIn('id', $spelerIds)->pluck('first_name', 'id');

        return $this->visible->query($user)
            ->with(['group.players', 'trainers', 'slot', 'attendances'])
            ->upcoming()
            ->limit($limiet)
            ->get()
            ->map(function (Training $training) use ($spelerIds, $namen) {
                $eigen = $training->group
                    ? $training->group->players->whereIn('id', $spelerIds)->pluck('id')
                    : collect([$training->slot?->player_id])->filter();

                $aanwezigheid = $training->attendances->keyBy('player_id');

                return [
                    'id' => $training->id,
                    'label' => $training->label(),
                    'for' => $eigen->map(fn ($id) => $namen[$id] ?? null)->filter()->join(' en '),
                    // Per kind: wat is er doorgegeven, zodat je kunt afmelden of
                    // weer aanmelden zonder eerst de training te openen.
                    'children' => $eigen->map(fn ($id) => [
                        'id' => $id,
                        'first_name' => $namen[$id] ?? '',
                        'registration' => $aanwezigheid->get($id)?->registration?->value,
                    ])->values()->all(),
                    'date' => $training->starts_at->translatedFormat('l j F'),
                    'is_today' => $training->starts_at->isToday(),
                    'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
                    'location' => $training->location,
                    'trainers' => $training->trainers->pluck('name')->all(),
                    'cancelled' => $training->isCancelled(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Wat er nú van een ouder gevraagd wordt.
     *
     * Alleen dingen met een knop: een rekening die openstaat. Geen cijfers ter
     * informatie - daar komt hij niet voor. Ongelezen berichten staan hier
     * niet: die staan al bij het belletje en in het blok met berichten.
     *
     * De vorm is die van het aandacht-blok van de school (AttentionPanel), met
     * dezelfde tonen: te laat is `danger`, openstaand `warning`.
     *
     * @param  list<int>  $spelerIds
     * @return list<array<string, mixed>>
     */
    public function attention(User $user, array $spelerIds): array
    {
        $items = [];

        if ($this->features->enabled(Feature::Betalingen, $user->school)) {
            $open = Payment::whereIn('player_id', $spelerIds)
                ->where('status', PaymentStatus::Open->value)
                ->with('player')
                ->orderBy('due_on')
                ->get();

            if ($open->isNotEmpty()) {
                $teLaat = $open->filter(fn (Payment $betaling) => $betaling->isOverdue());
                $bedrag = Money::format((int) $open->sum('amount_cents'));
                $kinderen = $open->map(fn (Payment $b) => $b->player?->first_name)->filter()->unique()->join(' en ');

                $items[] = [
                    'key' => 'payments',
                    'tone' => $teLaat->isNotEmpty() ? 'danger' : 'warning',
                    'icon' => 'payment',
                    'title' => $open->count() === 1
                        ? "Een rekening van {$bedrag} staat open"
                        : "{$open->count()} rekeningen staan open, samen {$bedrag}",
                    'body' => $kinderen === '' ? 'Voor je kind.' : 'Voor '.$kinderen.'.',
                    'href' => '/billing',
                    'action' => 'Bekijken',
                ];
            }
        }

        return $items;
    }

    /**
     * Waar een ouder zijn kind nu voor kan inschrijven.
     *
     * Alleen wat openstaat en waar nog plek is: iets tonen waar je je niet op
     * kunt aanmelden is een dode klik.
     *
     * @return list<array<string, mixed>>
     */
    public function openOfferings(User $user, int $limiet = 3): array
    {
        if (! $this->features->enabled(Feature::Betalingen, $user->school)) {
            return [];
        }

        return Product::query()
            ->where('is_active', true)
            ->purchasable()
            ->withCount(['participations' => fn ($q) => $q->confirmed()])
            ->orderByRaw('starts_on is null')
            ->orderBy('starts_on')
            ->get()
            ->filter(fn (Product $aanbod) => $aanbod->acceptsSignups())
            ->take($limiet)
            ->map(fn (Product $aanbod) => [
                'id' => $aanbod->id,
                'name' => $aanbod->name,
                'type' => $aanbod->type->label(),
                'description' => $aanbod->description,
                'amount' => Money::format($aanbod->amount_cents),
                'is_free' => $aanbod->amount_cents === 0,
                'billing' => $aanbod->billing_type->short(),
                'period' => $this->periode($aanbod),
                'location' => $aanbod->location,
                'spots_left' => $aanbod->spotsLeft(),
                'image' => $aanbod->image_url,
                // Direct de inschrijving van dít aanbod in, niet een overzicht.
                'enroll_url' => route('enroll.show', $user->school).'?aanbod='.$aanbod->id,
            ])
            ->values()
            ->all();
    }

    protected function periode(Product $aanbod): ?string
    {
        if ($aanbod->starts_on === null) {
            return null;
        }

        $start = $aanbod->starts_on->translatedFormat('j F');

        return $aanbod->ends_on === null || $aanbod->ends_on->isSameDay($aanbod->starts_on)
            ? $start
            : $start.' t/m '.$aanbod->ends_on->translatedFormat('j F');
    }

    /**
     * De laatste berichten van de school, met ongelezen voorop gemarkeerd.
     *
     * Dezelfde bron als het belletje in de balk: dat moet hetzelfde zeggen als
     * dit blok, anders staat er een rood bolletje bij een leeg scherm.
     *
     * @return list<array<string, mixed>>
     */
    public function messages(User $user, int $limiet = 4): array
    {
        return $user->notifications()
            ->latest()
            ->limit($limiet)
            ->get()
            ->map(fn ($melding) => [
                'id' => $melding->id,
                'title' => $melding->data['title'] ?? 'Bericht van de school',
                'url' => $melding->data['url'] ?? '/notifications',
                'when' => $melding->created_at->diffForHumans(),
                'unread' => $melding->read_at === null,
            ])
            ->values()
            ->all();
    }
}
