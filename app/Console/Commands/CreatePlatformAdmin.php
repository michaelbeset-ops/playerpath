<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Het account waarmee je het platform beheert.
 *
 * Alleen via de commandoregel, net als school:create. Een platformbeheerder
 * aanmaken vanuit een scherm zou betekenen dat er een scherm bestaat waarmee
 * iemand zichzelf boven alle scholen kan zetten, en dat hoort niet te bestaan.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin
                            {--name= : Naam van de beheerder}
                            {--email= : E-mailadres}
                            {--password= : Wachtwoord}';

    protected $description = 'Maak een account aan dat het hele platform beheert';

    public function handle(): int
    {
        $naam = $this->option('name') ?: $this->ask('Hoe heet je?');
        $email = $this->option('email') ?: $this->ask('Wat is je e-mailadres?');
        $wachtwoord = $this->option('password') ?: $this->secret('Kies een wachtwoord');

        $validator = Validator::make([
            'name' => $naam,
            'email' => $email,
            'password' => $wachtwoord,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ], [], [
            'name' => 'De naam',
            'email' => 'Het e-mailadres',
            'password' => 'Het wachtwoord',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $fout) {
                $this->error($fout);
            }

            return self::FAILURE;
        }

        SpatieRole::findOrCreate(Role::Platformbeheerder->value, 'web');

        $beheerder = User::create([
            // Nadrukkelijk geen school: dit account hoort nergens bij en kan
            // daardoor ook nooit per ongeluk als gewone gebruiker data van één
            // school meekrijgen.
            'school_id' => null,
            'name' => $naam,
            'email' => $email,
            'password' => $wachtwoord,
            'email_verified_at' => now(),
        ]);

        $beheerder->assignRole(Role::Platformbeheerder->value);

        $this->info("Platformbeheerder {$naam} is aangemaakt.");
        $this->line('  Inloggen doe je op de gewone inlogpagina; je komt dan uit op /beheer.');

        return self::SUCCESS;
    }
}
