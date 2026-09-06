<?php

namespace App\Support\Dashboard;

use App\Enums\DashboardWidget;
use App\Enums\Feature;
use App\Models\User;
use App\Support\Features\Features;

/**
 * Welke widgets er voor deze gebruiker bestaan, en waar ze standaard staan.
 *
 * Eén plek, zoals bij het menu en de functies per school. Een widget die je
 * niet mag zien bestaat hier niet — dan kan hij ook niet in een opgeslagen
 * indeling opduiken en kan het scherm er niet per ongeluk cijfers voor
 * berekenen.
 *
 * ## De standaardindeling staat in code, niet in de database
 *
 * Wie niets heeft ingesteld krijgt hem, en een widget die er later bijkomt
 * verschijnt dan vanzelf. Zou de standaard bij het aanmaken van een account
 * worden weggeschreven, dan zag een bestaande school nieuwe widgets nooit.
 */
class WidgetRegistry
{
    /** Het raster is twaalf kolommen breed; op mobiel wordt het er één. */
    public const KOLOMMEN = 12;

    public function __construct(protected Features $features) {}

    /**
     * De standaardindeling: hiërarchie van boven naar beneden.
     *
     * Vier kerncijfers naast elkaar, daaronder ontwikkeling breed naast
     * financieel smal, en onderaan klein wat er nog bij komt kijken.
     *
     * @return list<array{widget: DashboardWidget, x: int, y: int, w: int}>
     */
    public function defaultLayout(): array
    {
        return [
            ['widget' => DashboardWidget::KpiPlayers, 'x' => 0, 'y' => 0, 'w' => 3],
            ['widget' => DashboardWidget::KpiRating, 'x' => 3, 'y' => 0, 'w' => 3],
            ['widget' => DashboardWidget::KpiReports, 'x' => 6, 'y' => 0, 'w' => 3],
            ['widget' => DashboardWidget::KpiRevenue, 'x' => 9, 'y' => 0, 'w' => 3],
            ['widget' => DashboardWidget::Development, 'x' => 0, 'y' => 3, 'w' => 8],
            ['widget' => DashboardWidget::Finance, 'x' => 8, 'y' => 3, 'w' => 4],
            ['widget' => DashboardWidget::Trainings, 'x' => 0, 'y' => 10, 'w' => 6],
            ['widget' => DashboardWidget::Birthdays, 'x' => 6, 'y' => 10, 'w' => 6],
        ];
    }

    /**
     * De indeling van deze gebruiker: de opgeslagen indeling, of de standaard.
     *
     * Widgets die deze gebruiker niet mag zien vallen eruit, en de gaten die
     * dat achterlaat worden niet opgevuld — dat zou betekenen dat het scherm
     * van een trainer er anders uitziet dan hij het heeft neergezet.
     *
     * @return list<array{key: string, size: int, height: int, x: int, y: int}>
     */
    public function layoutFor(User $user): array
    {
        $opgeslagen = $user->dashboard_layout['widgets'] ?? null;

        $rijen = is_array($opgeslagen) && $opgeslagen !== []
            ? $this->uitOpslag($opgeslagen)
            : $this->defaultLayout();

        $zichtbaar = array_filter($rijen, fn (array $rij) => $this->available($user, $rij['widget']));

        return array_values(array_map(fn (array $rij) => [
            'key' => $rij['widget']->value,
            'size' => $rij['w'],
            'height' => $rij['widget']->height(),
            'x' => $rij['x'],
            'y' => $rij['y'],
        ], $zichtbaar));
    }

    /**
     * De widgets die deze gebruiker daadwerkelijk te zien krijgt.
     *
     * Het scherm rekent alleen uit wat hierin zit: een widget die uitstaat
     * kost geen enkele query.
     *
     * @return list<DashboardWidget>
     */
    public function visible(User $user): array
    {
        return array_values(array_map(
            fn (array $rij) => DashboardWidget::from($rij['key']),
            $this->layoutFor($user),
        ));
    }

    public function shows(User $user, DashboardWidget $widget): bool
    {
        return in_array($widget, $this->visible($user), true);
    }

    /**
     * Alles wat deze gebruiker zou mógen plaatsen, met wat erover te vertellen
     * valt. Straks de lijst achter "+ Widget toevoegen".
     *
     * @return list<array<string, mixed>>
     */
    public function describe(User $user): array
    {
        return array_values(array_map(fn (DashboardWidget $widget) => [
            'key' => $widget->value,
            'label' => $widget->label(),
            'description' => $widget->description(),
            'icon' => $widget->icon(),
            'sizes' => $widget->sizes(),
            'height' => $widget->height(),
        ], array_filter(
            DashboardWidget::cases(),
            fn (DashboardWidget $widget) => $this->available($user, $widget),
        )));
    }

    /**
     * Opgeslagen rijen terugbrengen tot iets dat bestaat.
     *
     * Een sleutel die niet meer bestaat verdwijnt, en een breedte die niet mag
     * valt terug op de standaard. Zo kan een oude indeling het scherm nooit
     * stukmaken.
     *
     * @param  list<array<string, mixed>>  $rijen
     * @return list<array{widget: DashboardWidget, x: int, y: int, w: int}>
     */
    protected function uitOpslag(array $rijen): array
    {
        $schoon = [];

        foreach ($rijen as $rij) {
            $widget = DashboardWidget::tryFrom((string) ($rij['key'] ?? ''));

            if ($widget === null) {
                continue;
            }

            $breedte = (int) ($rij['w'] ?? 0);

            $schoon[] = [
                'widget' => $widget,
                'x' => max(0, min(self::KOLOMMEN - 1, (int) ($rij['x'] ?? 0))),
                'y' => max(0, (int) ($rij['y'] ?? 0)),
                'w' => in_array($breedte, $widget->sizes(), true) ? $breedte : $widget->defaultSize(),
            ];
        }

        return $schoon;
    }

    protected function available(User $user, DashboardWidget $widget): bool
    {
        if ($widget->ownerOnly() && ! $user->isEigenaar()) {
            return false;
        }

        $feature = $widget->feature();

        return $feature === null || $this->features->enabled($feature, $user->school);
    }

    /** Puur voor de leesbaarheid van aanroepers. */
    public function feature(DashboardWidget $widget): ?Feature
    {
        return $widget->feature();
    }
}
