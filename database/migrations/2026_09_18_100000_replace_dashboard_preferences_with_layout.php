<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Van vinkjes naar een indeling.
 *
 * Het dashboard was in te richten met vinkjes in Instellingen: veertien
 * kerncijfers en vijf blokken aan of uit. Dat wordt vervangen door widgets die
 * je op het dashboard zelf neerzet. Twee schermen die hetzelfde doen lopen
 * vroeg of laat uit de pas, dus het oude gaat weg.
 *
 * Wat iemand had uitgezet blijft uit: de oude sleutels worden omgezet naar de
 * nieuwe widgets. Wat geen tegenhanger heeft (keepers, groepen, doelen,
 * openstaand als los cijfer) verdwijnt — die cijfers staan nu in het
 * financiële blok of in het aandacht-blok, en niet meer als losse tegel.
 */
return new class extends Migration
{
    /** oude sleutel => nieuwe widget */
    private const VERTALING = [
        'tiles' => [
            'players' => 'kpi_players',
            'rating' => 'kpi_rating',
            'reports' => 'kpi_reports',
            'revenue' => 'kpi_revenue',
        ],
        'blocks' => [
            'finance' => 'finance',
            'trainings' => 'trainings',
            'birthdays' => 'birthdays',
        ],
    ];

    /** De standaardindeling; zie WidgetRegistry::defaultLayout(). */
    private const STANDAARD = [
        ['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3],
        ['key' => 'kpi_rating', 'x' => 3, 'y' => 0, 'w' => 3],
        ['key' => 'kpi_reports', 'x' => 6, 'y' => 0, 'w' => 3],
        ['key' => 'kpi_revenue', 'x' => 9, 'y' => 0, 'w' => 3],
        ['key' => 'development', 'x' => 0, 'y' => 3, 'w' => 8],
        ['key' => 'finance', 'x' => 8, 'y' => 3, 'w' => 4],
        ['key' => 'trainings', 'x' => 0, 'y' => 10, 'w' => 6],
        ['key' => 'birthdays', 'x' => 6, 'y' => 10, 'w' => 6],
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_layout')->nullable()->after('dashboard_preferences');
        });

        // Alleen wie echt iets had ingesteld krijgt een eigen indeling. De rest
        // blijft leeg en volgt de standaard, zodat een widget die er later
        // bijkomt bij hen vanzelf verschijnt.
        DB::table('users')->whereNotNull('dashboard_preferences')->orderBy('id')
            ->each(function ($rij) {
                $oud = json_decode((string) $rij->dashboard_preferences, true);

                if (! is_array($oud) || $oud === []) {
                    return;
                }

                $uit = [];

                foreach (self::VERTALING as $soort => $paren) {
                    foreach ($paren as $oudeSleutel => $widget) {
                        if (($oud[$soort][$oudeSleutel] ?? true) === false) {
                            $uit[] = $widget;
                        }
                    }
                }

                if ($uit === []) {
                    return;
                }

                $indeling = array_values(array_filter(
                    self::STANDAARD,
                    fn (array $widget) => ! in_array($widget['key'], $uit, true),
                ));

                DB::table('users')->where('id', $rij->id)->update([
                    'dashboard_layout' => json_encode(['widgets' => $indeling]),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_preferences')->nullable();
            $table->dropColumn('dashboard_layout');
        });
    }
};
