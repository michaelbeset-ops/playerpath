<?php

namespace App\Actions\Offerings;

use App\Models\Group;
use App\Models\Product;
use App\Models\Training;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Het rooster van een aanbod: een groep en de trainingen die erin staan.
 *
 * Hier zit de knoop tussen het nieuwe begrip "aanbod" en alles wat er al was.
 * Een blok, een kamp of een small group krijgt **een gewone groep**, en de
 * trainingen hangen daaronder. Daardoor blijven aanwezigheid, rapporten, de
 * agenda en Mijn trainingen werken zoals ze altijd al deden — er is geen tweede
 * soort training bijgekomen die overal apart behandeld moet worden.
 *
 * Drie afspraken die je niet moet omdraaien:
 *
 * 1. **Losse trainingen, geen reeks-entiteit.** Net als bij wekelijks herhalen
 *    in het roosterscherm: een training afzeggen of verplaatsen raakt de rest
 *    niet. Er is dus ook geen "pas de hele serie aan".
 * 2. **Opnieuw roosteren raakt alleen wat nog moet komen.** Trainingen die al
 *    geweest zijn dragen aanwezigheid; die gooi je niet weg omdat iemand een
 *    tijdstip aanpast.
 * 3. **De groep blijft bestaan na afloop.** De historie van een speler hangt
 *    eraan. Een afgelopen blok zet je op niet-actief, je verwijdert het niet.
 */
class ScheduleOffering
{
    /**
     * @param  list<string>  $dates  losse dagen (kamp), als 'Y-m-d'
     * @param  list<int>  $weekdays  0 (zondag) t/m 6, voor een wekelijkse reeks
     * @return int het aantal trainingen dat erbij kwam
     */
    public function handle(
        Product $product,
        array $weekdays = [],
        array $dates = [],
        string $startsAt = '18:00',
        string $endsAt = '19:30',
    ): int {
        if (! $product->type->hasSchedule()) {
            return 0;
        }

        $groep = $this->group($product);

        $momenten = $dates !== []
            ? collect($dates)->map(fn (string $dag) => CarbonImmutable::parse($dag))
            : $this->weekly($product, $weekdays);

        // Wat al geweest is blijft staan; alleen de toekomst wordt opnieuw
        // gelegd, anders verdwijnt afgevinkte aanwezigheid.
        $bestaand = Training::where('group_id', $groep->id)
            ->where('starts_at', '>=', now())
            ->get()
            ->keyBy(fn (Training $training) => $training->starts_at->format('Y-m-d'));

        $trainers = $product->trainers->pluck('id')->all();
        $nieuw = 0;

        DB::transaction(function () use ($momenten, $groep, $product, $startsAt, $endsAt, $bestaand, $trainers, &$nieuw) {
            foreach ($momenten as $dag) {
                if ($dag->isPast() || $bestaand->has($dag->format('Y-m-d'))) {
                    continue;
                }

                $training = Training::create([
                    'group_id' => $groep->id,
                    'starts_at' => $dag->setTimeFromTimeString($startsAt),
                    'ends_at' => $dag->setTimeFromTimeString($endsAt),
                    'location' => $product->location,
                ]);

                $training->trainers()->sync($trainers);

                $nieuw++;
            }
        });

        return $nieuw;
    }

    /**
     * De groep van dit aanbod, zo nodig aangemaakt.
     *
     * De naam volgt het aanbod, zodat een trainer in het rooster ziet waar hij
     * staat. Hij wordt bijgewerkt als het aanbod een andere naam krijgt: twee
     * namen voor hetzelfde ding is precies hoe een rooster onleesbaar wordt.
     */
    public function group(Product $product): Group
    {
        $groep = $product->group;

        if ($groep === null) {
            return Group::create([
                'product_id' => $product->id,
                'name' => $product->name,
                'is_active' => true,
            ]);
        }

        if ($groep->name !== $product->name) {
            $groep->update(['name' => $product->name]);
        }

        return $groep;
    }

    /**
     * De wekelijkse momenten tussen begin en eind.
     *
     * @param  list<int>  $weekdays
     * @return Collection<int, CarbonImmutable>
     */
    protected function weekly(Product $product, array $weekdays)
    {
        if ($weekdays === [] || $product->starts_on === null || $product->ends_on === null) {
            return collect();
        }

        $dagen = collect();
        $dag = CarbonImmutable::parse($product->starts_on)->startOfDay();
        $eind = CarbonImmutable::parse($product->ends_on)->endOfDay();

        while ($dag <= $eind) {
            if (in_array($dag->dayOfWeek, $weekdays, strict: true)) {
                $dagen->push($dag);
            }

            $dag = $dag->addDay();
        }

        return $dagen;
    }
}
