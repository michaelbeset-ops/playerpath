<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardBlock;
use App\Enums\DashboardTile;
use App\Enums\Feature;
use App\Models\User;
use App\Support\Features\Features;

/**
 * Wat iemand op zijn dashboard wil zien.
 *
 * Per **gebruiker**, niet per school: een trainer kijkt naar zijn rapporten en
 * de eigenaar naar zijn omzet, en die twee op één instelling zetten betekent
 * dat er altijd één van de twee ontevreden is.
 *
 * Drie regels, met opzet dezelfde als bij de functies per school:
 *
 * 1. **Er worden alleen afwijkingen opgeslagen.** Wie niets instelt krijgt de
 *    standaard. Wie wél iets instelt krijgt een nieuwe tegel later volgens
 *    diens eigen standaard — anders zou iemand die ooit één vinkje zette
 *    nooit meer iets nieuws te zien krijgen.
 * 2. **Je kunt alleen aanzetten wat je mag zien.** Een trainer krijgt de
 *    omzettegel niet eens aangeboden, en een school zonder de functie
 *    Betalingen ziet hem ook niet. Dat is geen cosmetica: de cijfers worden
 *    dan ook niet berekend.
 * 3. **De volgorde ligt vast** (de volgorde van de enum). Zelf slepen klinkt
 *    aardig, maar het is werk dat niemand vraagt en het maakt de opslag
 *    breekbaar.
 */
class DashboardPreferences
{
    public function __construct(protected Features $features) {}

    /**
     * De tegels die deze gebruiker te zien krijgt.
     *
     * @return list<DashboardTile>
     */
    public function tiles(User $user): array
    {
        return array_values(array_filter(
            DashboardTile::cases(),
            fn (DashboardTile $tegel) => $this->beschikbaar($user, $tegel->feature(), $tegel->ownerOnly())
                && $this->aan($user, 'tiles', $tegel->value, $tegel->defaultVisible()),
        ));
    }

    /**
     * De panelen die deze gebruiker te zien krijgt.
     *
     * @return list<DashboardBlock>
     */
    public function blocks(User $user): array
    {
        return array_values(array_filter(
            DashboardBlock::cases(),
            fn (DashboardBlock $blok) => $this->beschikbaar($user, $blok->feature(), $blok->ownerOnly())
                && $this->aan($user, 'blocks', $blok->value, $blok->defaultVisible()),
        ));
    }

    public function showsBlock(User $user, DashboardBlock $blok): bool
    {
        return in_array($blok, $this->blocks($user), true);
    }

    /**
     * Alles wat deze gebruiker kán aanzetten, met de stand van nu.
     *
     * @return array{tiles: list<array<string, mixed>>, blocks: list<array<string, mixed>>}
     */
    public function describe(User $user): array
    {
        return [
            'tiles' => array_values(array_map(
                fn (DashboardTile $tegel) => [
                    'key' => $tegel->value,
                    'label' => $tegel->label(),
                    'description' => $tegel->description(),
                    'enabled' => $this->aan($user, 'tiles', $tegel->value, $tegel->defaultVisible()),
                ],
                array_filter(
                    DashboardTile::cases(),
                    fn (DashboardTile $t) => $this->beschikbaar($user, $t->feature(), $t->ownerOnly()),
                ),
            )),
            'blocks' => array_values(array_map(
                fn (DashboardBlock $blok) => [
                    'key' => $blok->value,
                    'label' => $blok->label(),
                    'description' => $blok->description(),
                    'enabled' => $this->aan($user, 'blocks', $blok->value, $blok->defaultVisible()),
                ],
                array_filter(
                    DashboardBlock::cases(),
                    fn (DashboardBlock $b) => $this->beschikbaar($user, $b->feature(), $b->ownerOnly()),
                ),
            )),
        ];
    }

    /**
     * Opslaan wat er is aangevinkt.
     *
     * Alleen sleutels die deze gebruiker mag zien komen erin: anders zou een
     * trainer via het formulier alsnog de omzettegel kunnen aanzetten.
     *
     * @param  array<string, bool>  $tiles
     * @param  array<string, bool>  $blocks
     */
    public function save(User $user, array $tiles, array $blocks): void
    {
        $bewaard = [
            'tiles' => $this->schoon($user, $tiles, DashboardTile::cases()),
            'blocks' => $this->schoon($user, $blocks, DashboardBlock::cases()),
        ];

        $user->forceFill(['dashboard_preferences' => $bewaard])->save();
    }

    /**
     * @param  array<string, bool>  $ingevuld
     * @param  list<DashboardTile|DashboardBlock>  $cases
     * @return array<string, bool>
     */
    protected function schoon(User $user, array $ingevuld, array $cases): array
    {
        $schoon = [];

        foreach ($cases as $case) {
            if (! array_key_exists($case->value, $ingevuld)) {
                continue;
            }

            if (! $this->beschikbaar($user, $case->feature(), $case->ownerOnly())) {
                continue;
            }

            $schoon[$case->value] = (bool) $ingevuld[$case->value];
        }

        return $schoon;
    }

    /** Mag deze gebruiker dit überhaupt zien? */
    protected function beschikbaar(User $user, ?Feature $feature, bool $ownerOnly): bool
    {
        if ($ownerOnly && ! $user->isEigenaar()) {
            return false;
        }

        return $feature === null || $this->features->enabled($feature, $user->school);
    }

    /** Onbekende sleutel: de standaard van die tegel of dat blok. */
    protected function aan(User $user, string $soort, string $sleutel, bool $standaard): bool
    {
        $voorkeuren = $user->dashboard_preferences[$soort] ?? [];

        return (bool) ($voorkeuren[$sleutel] ?? $standaard);
    }
}
