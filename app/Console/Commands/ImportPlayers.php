<?php

namespace App\Console\Commands;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Rating\AgeCategory;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Spelers, groepen en trainers uit een Excel-bestand in een school zetten.
 *
 * Voor een school die overstapt en haar ledenlijst in Excel heeft. Het bestand
 * heeft een kopregel met de kolommen Keepers (of Naam), Team, Dag en Trainers:
 *
 * - elke naam wordt een keeper; de trainingsdag ("Vrijdag 1") wordt de groep;
 * - de geboortedatum staat er niet in maar is verplicht. Hij wordt geschat
 *   uit het team (O12 wordt een geboortejaar dat in O12 valt); zonder team
 *   staat de speler in de uitvoer om na te lopen;
 * - een trainer krijgt een account zonder e-mailadres en zonder inlog. Hij
 *   staat bij Personeel en kan aan trainingen gekoppeld worden; inloggen kan
 *   pas na een uitnodiging.
 *
 * Nog eens draaien voegt niets dubbel toe: spelers en trainers worden op naam
 * herkend. Met --proef zie je wat er zou gebeuren, zonder iets op te slaan.
 * Er gaat geen enkele mail uit.
 */
class ImportPlayers extends Command
{
    protected $signature = 'playerpath:importeer-spelers
                            {school : De slug van de school}
                            {bestand : Pad naar het Excel-bestand (.xlsx) op de server}
                            {--proef : Alleen laten zien wat er zou gebeuren}';

    protected $description = 'Zet keepers, groepen en trainers uit een Excel-bestand in een school';

    public function handle(Tenancy $tenancy): int
    {
        $school = School::query()->where('slug', $this->argument('school'))->first();

        if ($school === null) {
            $this->line('Die school bestaat niet. Kies een van deze slugs:');
            School::query()->orderBy('name')->get(['name', 'slug'])
                ->each(fn (School $s) => $this->line("  {$s->slug}  ({$s->name})"));

            return self::FAILURE;
        }

        $pad = (string) $this->argument('bestand');

        if (! is_file($pad)) {
            $this->line("Het bestand {$pad} staat niet op de server.");

            return self::FAILURE;
        }

        [$spelers, $trainers] = $this->lees($pad);

        if ($spelers === []) {
            $this->line('Er staan geen spelers in het bestand. Heeft de eerste regel de kolom "Keepers" of "Naam"?');

            return self::FAILURE;
        }

        $tenancy->set($school);

        $proef = (bool) $this->option('proef');
        $uitkomst = ['nieuw' => 0, 'bestond' => 0, 'geschat' => [], 'groepen' => [], 'trainers_nieuw' => 0];

        DB::beginTransaction();

        try {
            $uitkomst = $this->importeer($school, $spelers, $trainers, $uitkomst);
        } catch (\Throwable $fout) {
            DB::rollBack();

            throw $fout;
        }

        $proef ? DB::rollBack() : DB::commit();

        $this->line(($proef ? 'PROEF, er is niets opgeslagen. ' : '')."School: {$school->name}");
        $this->line("Nieuwe spelers: {$uitkomst['nieuw']} (al aanwezig: {$uitkomst['bestond']})");

        foreach ($uitkomst['groepen'] as $naam => $aantal) {
            $this->line("  {$naam}: {$aantal} spelers");
        }

        $this->line('Nieuwe trainers (zonder inlog): '.$uitkomst['trainers_nieuw'].' van '.count($trainers));

        if ($uitkomst['geschat'] !== []) {
            $this->line('Zonder team, geboortedatum op 1 januari '.$this->jaarZonderTeam().' gezet. Loop deze na:');
            $this->line('  '.implode(', ', $uitkomst['geschat']));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{naam: string, team: ?string, dag: ?string}>  $spelers
     * @param  list<string>  $trainers
     * @param  array<string, mixed>  $uitkomst
     * @return array<string, mixed>
     */
    protected function importeer(School $school, array $spelers, array $trainers, array $uitkomst): array
    {
        $groepen = [];

        foreach ($spelers as $rij) {
            [$voornaam, $achternaam] = $this->splitsNaam($rij['naam']);

            $speler = Player::query()
                ->where('first_name', $voornaam)
                ->where('last_name', $achternaam)
                ->first();

            if ($speler === null) {
                $geboren = $this->geboortedatum($rij['team']);

                if ($geboren === null) {
                    $uitkomst['geschat'][] = $rij['naam'];
                }

                $speler = Player::create([
                    'first_name' => $voornaam,
                    'last_name' => $achternaam,
                    'date_of_birth' => $geboren ?? $this->jaarZonderTeam().'-01-01',
                    'position' => PlayerPosition::Keeper,
                    'is_active' => true,
                ]);
                $uitkomst['nieuw']++;
            } else {
                $uitkomst['bestond']++;
            }

            if ($rij['dag'] !== null) {
                $groep = $groepen[$rij['dag']] ??= Group::query()->firstOrCreate(
                    ['name' => $rij['dag']],
                    ['is_active' => true],
                );

                $groep->players()->syncWithoutDetaching([$speler->id]);
            }
        }

        foreach ($groepen as $naam => $groep) {
            $uitkomst['groepen'][$naam] = $groep->players()->count();
        }

        foreach ($trainers as $naam) {
            $bestaat = User::ofCurrentSchool()->where('name', $naam)->role(Role::Trainer->value)->exists();

            if ($bestaat) {
                continue;
            }

            // Geen adres en een wachtwoord dat niemand kent: dit account kan
            // niet inloggen en krijgt nooit mail.
            $trainer = User::create([
                'school_id' => $school->id,
                'name' => $naam,
                'email' => null,
                'password' => Str::password(40),
            ]);
            $trainer->assignRole(Role::Trainer->value);
            $uitkomst['trainers_nieuw']++;
        }

        return $uitkomst;
    }

    /**
     * @return array{0: list<array{naam: string, team: ?string, dag: ?string}>, 1: list<string>}
     */
    protected function lees(string $pad): array
    {
        $reader = new Reader;
        $reader->open($pad);

        $spelers = [];
        $trainers = [];
        $kolommen = null;

        foreach ($reader->getSheetIterator() as $blad) {
            foreach ($blad->getRowIterator() as $regel) {
                $cellen = array_map(fn ($waarde) => trim((string) $waarde), $regel->toArray());

                if ($kolommen === null) {
                    $kolommen = $this->kolommen($cellen);

                    continue;
                }

                $naam = $this->cel($cellen, $kolommen['naam']);

                if ($naam !== null) {
                    $spelers[] = [
                        'naam' => preg_replace('/\s+/', ' ', $naam),
                        'team' => $this->cel($cellen, $kolommen['team']),
                        // "vrijdag 1" en "Vrijdag  1" zijn dezelfde groep.
                        'dag' => ($dag = $this->cel($cellen, $kolommen['dag'])) === null
                            ? null
                            : Str::ucfirst(Str::lower(preg_replace('/\s+/', ' ', $dag))),
                    ];
                }

                $trainer = $this->cel($cellen, $kolommen['trainer']);

                if ($trainer !== null && ! in_array($trainer, $trainers, true)) {
                    $trainers[] = $trainer;
                }
            }

            break; // Alleen het eerste tabblad.
        }

        $reader->close();

        return [$spelers, $trainers];
    }

    /**
     * Welke kolom is wat, op de kopregel.
     *
     * @param  list<string>  $kop
     * @return array{naam: ?int, team: ?int, dag: ?int, trainer: ?int}
     */
    protected function kolommen(array $kop): array
    {
        $zoek = function (array $namen) use ($kop): ?int {
            foreach ($kop as $i => $titel) {
                if (in_array(Str::lower($titel), $namen, true)) {
                    return $i;
                }
            }

            return null;
        };

        return [
            'naam' => $zoek(['keepers', 'keeper', 'naam', 'speler', 'spelers']),
            'team' => $zoek(['team']),
            'dag' => $zoek(['dag', 'groep']),
            'trainer' => $zoek(['trainers', 'trainer']),
        ];
    }

    /** @param  list<string>  $cellen */
    protected function cel(array $cellen, ?int $kolom): ?string
    {
        if ($kolom === null) {
            return null;
        }

        $waarde = $cellen[$kolom] ?? '';

        return $waarde === '' ? null : $waarde;
    }

    /** @return array{0: string, 1: string} */
    protected function splitsNaam(string $naam): array
    {
        $delen = explode(' ', $naam, 2);

        return [$delen[0], $delen[1] ?? ''];
    }

    /**
     * Een geboortedatum die in de leeftijdscategorie van het team valt.
     *
     * "O12-1" en "MO14" geven 12 en 14. Een kind in O12 is dit seizoen tien
     * of elf; 1 januari van het jaar waarin het elf wordt valt daar altijd in.
     */
    protected function geboortedatum(?string $team): ?string
    {
        if ($team === null || ! preg_match('/O\s*-?\s*(\d{1,2})/i', $team, $m)) {
            return null;
        }

        return (AgeCategory::seasonStartYear(now()) + 1 - (int) $m[1]).'-01-01';
    }

    /** Zonder team: een keeper van rond de twaalf, zodat hij ergens in het midden valt. */
    protected function jaarZonderTeam(): int
    {
        return AgeCategory::seasonStartYear(now()) - 12;
    }
}
