<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * De vier rollen zijn platformbreed: ze horen niet bij één school.
 * Wie welke rol heeft staat per gebruiker, en die hoort wél bij één school.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
