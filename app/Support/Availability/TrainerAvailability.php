<?php

namespace App\Support\Availability;

use App\Enums\Daypart;
use App\Enums\Role;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Training;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Wanneer kan een trainer, en klopt de planning daarmee?
 *
 * Eén plek, zodat het invulscherm van de trainer, het overzicht van de eigenaar
 * en de waarschuwing op het dashboard nooit iets anders zeggen over dezelfde
 * dinsdagavond.
 *
 * Drie regels die dit bruikbaar houden:
 *
 * 1. **Niets ingevuld is onbekend, niet onbeschikbaar.** Anders kleurt bij elke
 *    school die dit nog niet gebruikt de hele planning rood, en dan kijkt
 *    niemand er meer naar. `hasSet()` maakt het verschil.
 * 2. **Een uitzondering wint van het ritme.** Dat is wat een uitzondering is.
 *    De meest specifieke wint: een uitzondering op één dagdeel gaat vóór een
 *    die de hele dag beslaat.
 * 3. **Het dagdeel gaat op de begintijd van de training.** Een training van
 *    16:30 tot 18:00 is een middagtraining; dat is het moment waarop de trainer
 *    er moet zijn.
 */
class TrainerAvailability
{
    /** Hoe ver het dashboard vooruitkijkt bij het waarschuwen. */
    public const VOORUIT_DAGEN = 14;

    /** Heeft deze trainer zijn beschikbaarheid ooit ingevuld? */
    public function hasSet(User $user): bool
    {
        return AvailabilityRule::where('user_id', $user->id)->exists()
            || AvailabilityException::where('user_id', $user->id)->exists();
    }

    /**
     * Kan deze trainer op dit moment?
     *
     * `null` betekent onbekend: hij heeft niets ingevuld, dus er valt niets
     * over te zeggen. Dat is bewust iets anders dan `false`.
     */
    public function isAvailableAt(User $user, CarbonInterface $moment): ?bool
    {
        if (! $this->hasSet($user)) {
            return null;
        }

        $uitzondering = $this->uitzonderingVoor($user, $moment);

        if ($uitzondering !== null) {
            return $uitzondering->available;
        }

        return AvailabilityRule::where('user_id', $user->id)
            ->where('weekday', $moment->dayOfWeekIso)
            ->where('daypart', Daypart::forTime($moment)->value)
            ->exists();
    }

    /**
     * De uitzondering die op dit moment geldt, de meest specifieke eerst.
     *
     * "Die zaterdag alleen 's ochtends niet" hoort te winnen van "die hele week
     * kan ik wel"; anders is de fijnere regel zinloos.
     */
    protected function uitzonderingVoor(User $user, CarbonInterface $moment): ?AvailabilityException
    {
        return AvailabilityException::where('user_id', $user->id)
            ->whereDate('starts_on', '<=', $moment->toDateString())
            ->whereDate('ends_on', '>=', $moment->toDateString())
            ->get()
            ->sortByDesc(fn (AvailabilityException $rij) => $rij->daypart === null ? 0 : 1)
            ->first(fn (AvailabilityException $rij) => $rij->covers($moment));
    }

    /**
     * Het rooster van één trainer als raster: per dag per dagdeel aan of uit.
     *
     * @return array<int, array<string, bool>>
     */
    public function grid(User $user): array
    {
        $rijen = AvailabilityRule::where('user_id', $user->id)->get();

        $raster = [];

        foreach (range(1, 7) as $dag) {
            foreach (Daypart::cases() as $dagdeel) {
                $raster[$dag][$dagdeel->value] = $rijen->contains(
                    fn (AvailabilityRule $rij) => $rij->weekday === $dag && $rij->daypart === $dagdeel
                );
            }
        }

        return $raster;
    }

    /**
     * De uitzonderingen die nog iets betekenen, voor het scherm.
     *
     * @return list<array<string, mixed>>
     */
    public function exceptions(User $user): array
    {
        return AvailabilityException::where('user_id', $user->id)
            ->current()
            ->get()
            ->map(fn (AvailabilityException $rij) => [
                'id' => $rij->id,
                'starts_on' => $rij->starts_on->toDateString(),
                'ends_on' => $rij->ends_on->toDateString(),
                'daypart' => $rij->daypart?->value,
                'available' => $rij->available,
                'note' => $rij->note,
                'label' => $rij->describe(),
            ])
            ->values()
            ->all();
    }

    /**
     * Alle trainers van de school met hun ritme, voor de eigenaar.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(): array
    {
        return $this->trainers()
            ->map(fn (User $trainer) => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'photo' => $trainer->photo_url,
                'has_set' => $this->hasSet($trainer),
                'grid' => $this->grid($trainer),
                'exceptions' => $this->exceptions($trainer),
                // Hoeveel dagdelen hij aan heeft staan. Nul terwijl has_set
                // waar is betekent dat hij bewust alles heeft uitgezet.
                'slots' => AvailabilityRule::where('user_id', $trainer->id)->count(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, User> */
    public function trainers(): Collection
    {
        return User::ofCurrentSchool()
            ->role([Role::Trainer->value, Role::Eigenaar->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * Waar de planning wringt: trainingen zonder trainer, en trainingen met een
     * trainer die op dat moment niet kan.
     *
     * Alleen vooruit, want een training van vorige week verplaats je niet meer.
     *
     * @return array{unavailable: list<array<string, mixed>>, unstaffed: list<array<string, mixed>>}
     */
    public function conflicts(int $dagen = self::VOORUIT_DAGEN): array
    {
        $trainingen = Training::query()
            ->whereNull('cancelled_at')
            ->whereBetween('starts_at', [now(), now()->addDays($dagen)->endOfDay()])
            ->with(['group', 'trainers'])
            ->orderBy('starts_at')
            ->get();

        $onbeschikbaar = [];
        $zonderTrainer = [];

        foreach ($trainingen as $training) {
            if ($training->trainers->isEmpty()) {
                // Een privétraining hoort bij één kind en heeft van zichzelf
                // geen trainer in het rooster nodig; die telt niet mee.
                if (! $training->isPrivate()) {
                    $zonderTrainer[] = $this->beschrijf($training);
                }

                continue;
            }

            foreach ($training->trainers as $trainer) {
                if ($this->isAvailableAt($trainer, $training->starts_at) === false) {
                    $onbeschikbaar[] = $this->beschrijf($training) + ['trainer' => $trainer->name];
                }
            }
        }

        return ['unavailable' => $onbeschikbaar, 'unstaffed' => $zonderTrainer];
    }

    /** @return array<string, mixed> */
    protected function beschrijf(Training $training): array
    {
        return [
            'id' => $training->id,
            'group' => $training->label(),
            'date' => $training->starts_at->translatedFormat('l j F'),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
        ];
    }
}
