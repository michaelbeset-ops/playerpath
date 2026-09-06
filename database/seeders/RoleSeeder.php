<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * De rollen zijn platformbreed: ze horen niet bij één school. Wie welke rol
 * heeft staat per gebruiker, en die hoort wél bij één school — behalve de
 * platformbeheerder, die juist geen school heeft.
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
