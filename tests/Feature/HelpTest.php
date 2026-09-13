<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * De hulppagina: voor iedereen die is ingelogd, met de uitleg voor zijn rol.
 */
class HelpTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);
    }

    public static function rollen(): array
    {
        return [
            'eigenaar' => [Role::Eigenaar, 'eigenaar'],
            'trainer' => [Role::Trainer, 'trainer'],
            'ouder' => [Role::Ouder, 'ouder'],
            'speler' => [Role::Speler, 'speler'],
        ];
    }

    #[DataProvider('rollen')]
    public function test_elke_rol_krijgt_de_uitleg_voor_zijn_rol(Role $rol, string $audience): void
    {
        $user = User::factory()->for($this->school)->create();
        $user->assignRole($rol->value);

        $this->actingAs($user)
            ->get('/help')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Help')
                ->where('audience', $audience)
                ->where('schoolName', $this->school->name)
                ->has('supportPhone')
            );

        // En hij staat in het menu, voor iedereen.
        $this->assertContains('/help', $this->navHrefs(app(MainNavigation::class)->for($user)));
    }

    public function test_een_ouder_krijgt_een_knop_naar_de_kaart_van_zijn_kind(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $kind = Player::factory()->for($this->school)->create();
        $kind->guardians()->attach($ouder->id);

        $this->actingAs($ouder)
            ->get('/help')
            ->assertInertia(fn ($page) => $page->where('firstChildId', $kind->id));
    }

    public function test_zonder_inlog_geen_hulp(): void
    {
        $this->get('/help')->assertRedirect('/login');
    }
}
