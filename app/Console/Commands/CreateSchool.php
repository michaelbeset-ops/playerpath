<?php

namespace App\Console\Commands;

use App\Actions\Onboarding\SeedDemoData;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Zelfregistratie staat dicht, dus scholen zet je hiermee op.
 * De onboarding-flow uit fase 8 bouwt hierop verder.
 */
class CreateSchool extends Command
{
    protected $signature = 'school:create
                            {name? : De naam van de school}
                            {--owner-name= : Naam van de eigenaar}
                            {--owner-email= : E-mailadres van de eigenaar}
                            {--password= : Wachtwoord van de eigenaar}';

    protected $description = 'Maak een nieuwe school aan met een eigenaar-account';

    public function handle(): int
    {
        $naam = $this->argument('name') ?: $this->ask('Hoe heet de school?');
        $eigenaarNaam = $this->option('owner-name') ?: $this->ask('Hoe heet de eigenaar?');
        $eigenaarEmail = $this->option('owner-email') ?: $this->ask('Wat is het e-mailadres van de eigenaar?');
        $wachtwoord = $this->option('password') ?: $this->secret('Kies een wachtwoord voor de eigenaar');

        $validator = Validator::make([
            'name' => $naam,
            'owner_name' => $eigenaarNaam,
            'owner_email' => $eigenaarEmail,
            'password' => $wachtwoord,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ], [], [
            'name' => 'De schoolnaam',
            'owner_name' => 'De naam van de eigenaar',
            'owner_email' => 'Het e-mailadres',
            'password' => 'Het wachtwoord',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $fout) {
                $this->error($fout);
            }

            return self::FAILURE;
        }

        if (SpatieRole::where('name', Role::Eigenaar->value)->doesntExist()) {
            $this->error('De rollen bestaan nog niet. Draai eerst: php artisan db:seed --class=RoleSeeder');

            return self::FAILURE;
        }

        $school = School::create([
            'name' => $naam,
            'slug' => $this->uniekeSlug($naam),
        ]);

        $eigenaar = User::create([
            'school_id' => $school->id,
            'name' => $eigenaarNaam,
            'email' => $eigenaarEmail,
            'password' => $wachtwoord,
            'email_verified_at' => now(),
        ]);

        $eigenaar->assignRole(Role::Eigenaar->value);

        // Een lege omgeving is de vijand: de eigenaar logt in op iets dat al
        // werkt, en ruimt het met één knop op zodra hij echt begint.
        app(SeedDemoData::class)->handle($school, $eigenaar);

        $this->newLine();
        $this->info("School '{$school->name}' is aangemaakt, met voorbeelddata om mee te beginnen.");
        $this->line("Eigenaar: {$eigenaar->name} <{$eigenaar->email}>");
        $this->line('Deze eigenaar kan nu inloggen op '.config('app.url').'/login');

        return self::SUCCESS;
    }

    protected function uniekeSlug(string $naam): string
    {
        $basis = Str::slug($naam);
        $slug = $basis;
        $nummer = 2;

        while (School::where('slug', $slug)->exists()) {
            $slug = $basis.'-'.$nummer++;
        }

        return $slug;
    }
}
