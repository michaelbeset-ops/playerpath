<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fase 0: één testaccount om mee in te loggen.
     * Scholen, rollen en spelers volgen in fase 1.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Michael Beset',
            'email' => 'test@playerpath.test',
            'password' => 'wachtwoord',
        ]);
    }
}
