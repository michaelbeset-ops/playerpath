<?php

namespace App\Console\Commands;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Een ouderaccount om op je eigen telefoon mee te testen, ook op productie.
 *
 * Zelfregistratie staat dicht en een uitnodiging vraagt een mailbox; voor
 * "even kijken hoe het er voor een ouder uitziet" is dat een omweg. Dit zet
 * in één keer neer: een ouder, aan een kind gekoppeld, met de kind-link erbij.
 *
 * Kies e-mail en wachtwoord zelf (--email, --wachtwoord): dan weet je de
 * login vooraf en hoef je hem niet uit de uitvoer van Forge te halen. Bestaat
 * het adres al als testouder, dan wordt alleen het wachtwoord opnieuw gezet.
 *
 * Het kind: met --speler een bestaande speler, anders de best gevulde echte
 * speler van de school, anders een voorbeeldspeler, en als er helemaal niets
 * is een nieuw testkind. Draai het nooit voor een echte ouder: die hoort een
 * uitnodiging te krijgen en zelf een wachtwoord te kiezen.
 */
class CreateTestParent extends Command
{
    protected $signature = 'playerpath:test-ouder
                            {school? : De slug van de school, bijvoorbeeld voetbalschool-playerpath}
                            {--email= : E-mailadres van de testouder (standaard een uniek testadres)}
                            {--wachtwoord= : Het wachtwoord (standaard een willekeurig wachtwoord)}
                            {--speler= : Het id van de speler die het kind wordt}';

    protected $description = 'Maak een ouderaccount met kind en kind-link om mee te testen';

    public function handle(Tenancy $tenancy): int
    {
        $slug = $this->argument('school');
        $scholen = School::query()->orderBy('name')->get(['name', 'slug']);

        if ($scholen->isEmpty()) {
            $this->line('Er is nog geen school. Maak er eerst een met: php artisan school:create');

            return self::FAILURE;
        }

        if ($slug === null) {
            // Geen fout: dit is de manier om de slug op te zoeken. Een rode
            // "Failed" in Forge zou lezen alsof er iets stuk is.
            $this->line('Welke school? Draai het opnieuw met een van deze slugs:');
            $scholen->each(fn (School $s) => $this->line("  {$s->slug}  ({$s->name})"));

            return self::SUCCESS;
        }

        $school = School::where('slug', $slug)->first();

        if ($school === null) {
            $this->line("Er is geen school met slug '{$slug}'. Deze bestaan wel:");
            $scholen->each(fn (School $s) => $this->line("  {$s->slug}  ({$s->name})"));

            return self::FAILURE;
        }

        $email = Str::lower($this->option('email') ?: 'testouder-'.Str::lower(Str::random(6)).'@playerpath.nl');
        $wachtwoord = $this->option('wachtwoord') ?: Str::password(14, symbols: false);

        if (mb_strlen($wachtwoord) < 8) {
            $this->line('Kies een wachtwoord van minstens 8 tekens.');

            return self::FAILURE;
        }

        $bestaand = User::where('email', $email)->first();

        if ($bestaand !== null && ($bestaand->school_id !== $school->id || ! $bestaand->isOuder())) {
            $this->line("Er is al een ander account met {$email}. Kies een ander adres met --email.");

            return self::FAILURE;
        }

        $tenancy->set($school);

        $speler = $this->kind();

        if ($speler === false) {
            $this->line('Die speler bestaat niet in deze school.');

            return self::FAILURE;
        }

        [$ouder, $speler] = DB::transaction(function () use ($school, $email, $wachtwoord, $speler, $bestaand) {
            if ($bestaand !== null) {
                // Opnieuw draaien met hetzelfde adres: het wachtwoord opnieuw zetten.
                $ouder = $bestaand;
                $ouder->forceFill(['password' => Hash::make($wachtwoord)])->save();
                $speler ??= $ouder->children()->first();
            } else {
                $ouder = User::create([
                    'school_id' => $school->id,
                    'name' => 'Testouder',
                    'email' => $email,
                    'password' => $wachtwoord,
                ]);
                $ouder->forceFill(['email_verified_at' => now()])->save();
                $ouder->assignRole(Role::Ouder->value);
            }

            $speler ??= $this->nieuwKind();
            $speler->guardians()->syncWithoutDetaching([$ouder->id => ['relationship' => 'verzorger']]);

            if (! $speler->hasChildLink()) {
                $speler->forceFill(['child_token' => Str::random(48), 'child_link_at' => now()])->save();
            }

            return [$ouder, $speler];
        });

        $url = rtrim((string) config('app.url'), '/');

        // Gewone regels, geen tabel: die leest in elk uitvoerscherm hetzelfde.
        $this->line('');
        $this->line("Testouder klaar bij {$school->name}.");
        $this->line('');
        $this->line('Inloggen:   '.$url.'/login');
        $this->line('E-mail:     '.$ouder->email);
        $this->line('Wachtwoord: '.$wachtwoord);
        $this->line('Kind:       '.$speler->full_name.($speler->overall_rating ? " (rating {$speler->overall_rating})" : ' (nog geen rapport)'));
        $this->line('Kind-link:  '.route('players.child', $speler->child_token));
        $this->line('');
        $this->line('Haal het account weg als je klaar bent met testen.');

        return self::SUCCESS;
    }

    /** @return Player|null|false false als --speler niet bestaat; null als er een nieuw kind moet komen */
    protected function kind(): Player|null|false
    {
        if ($id = $this->option('speler')) {
            return Player::whereKey($id)->first() ?? false;
        }

        // Liefst een echte speler met een gevulde kaart, dan een voorbeeldspeler.
        return Player::query()->active()->real()->whereNotNull('overall_rating')->orderByDesc('overall_rating')->first()
            ?? Player::query()->active()->whereNotNull('overall_rating')->orderByDesc('overall_rating')->first()
            ?? Player::query()->active()->first();
    }

    protected function nieuwKind(): Player
    {
        return Player::create([
            'first_name' => 'Test',
            'last_name' => 'Kind',
            'date_of_birth' => now()->subYears(10)->startOfYear()->toDateString(),
            'position' => PlayerPosition::Keeper->value,
            'is_active' => true,
        ]);
    }
}
