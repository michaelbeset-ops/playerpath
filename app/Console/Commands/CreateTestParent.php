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
use Illuminate\Support\Str;

/**
 * Een ouderaccount om op je eigen telefoon mee te testen, ook op productie.
 *
 * Zelfregistratie staat dicht en een uitnodiging vraagt een mailbox; voor
 * "even kijken hoe het er voor een ouder uitziet" is dat een omweg. Dit zet
 * in één keer neer: een ouder met een wachtwoord dat op het scherm komt, aan
 * een kind gekoppeld, met de kind-link erbij.
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
                            {--speler= : Het id van de speler die het kind wordt}';

    protected $description = 'Maak een ouderaccount met kind en kind-link om mee te testen';

    public function handle(Tenancy $tenancy): int
    {
        $slug = $this->argument('school');

        if ($slug === null) {
            // Geen fout: dit is de manier om de slug op te zoeken. Een rode
            // "Failed" in Forge zou lezen alsof er iets stuk is.
            $this->line('Welke school? Draai het opnieuw met een van deze slugs:');
            School::query()->orderBy('name')->get(['name', 'slug'])->each(fn (School $s) => $this->line("  {$s->slug}  ({$s->name})"));

            return self::SUCCESS;
        }

        $school = School::where('slug', $slug)->first();

        if ($school === null) {
            $this->error("Er is geen school met slug '{$slug}'.");

            return self::FAILURE;
        }

        $email = $this->option('email') ?: 'testouder-'.Str::lower(Str::random(6)).'@playerpath.nl';

        if (User::where('email', $email)->exists()) {
            $this->error("Er is al een account met {$email}. Kies een ander adres met --email.");

            return self::FAILURE;
        }

        $tenancy->set($school);

        $speler = $this->kind($school);

        if ($speler === false) {
            $this->error('Die speler bestaat niet in deze school.');

            return self::FAILURE;
        }

        $wachtwoord = Str::password(14, symbols: false);

        [$ouder, $speler] = DB::transaction(function () use ($school, $email, $wachtwoord, $speler) {
            $ouder = User::create([
                'school_id' => $school->id,
                'name' => 'Testouder',
                'email' => $email,
                'password' => $wachtwoord,
            ]);
            $ouder->forceFill(['email_verified_at' => now()])->save();
            $ouder->assignRole(Role::Ouder->value);

            $speler ??= $this->nieuwKind();
            $speler->guardians()->syncWithoutDetaching([$ouder->id => ['relationship' => 'verzorger']]);

            if (! $speler->hasChildLink()) {
                $speler->forceFill(['child_token' => Str::random(48), 'child_link_at' => now()])->save();
            }

            return [$ouder, $speler];
        });

        $url = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->info("Testouder aangemaakt bij {$school->name}.");
        $this->table(['', ''], [
            ['Inloggen', $url.'/login'],
            ['E-mail', $ouder->email],
            ['Wachtwoord', $wachtwoord],
            ['Kind', $speler->full_name.($speler->overall_rating ? " (rating {$speler->overall_rating})" : ' (nog geen rapport)')],
            ['Kind-link', route('players.child', $speler->child_token)],
        ]);
        $this->line('Dit wachtwoord staat nergens anders. Haal het account weg als je klaar bent met testen.');

        return self::SUCCESS;
    }

    /** @return Player|null|false false als --speler niet bestaat; null als er een nieuw kind moet komen */
    protected function kind(School $school): Player|null|false
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
