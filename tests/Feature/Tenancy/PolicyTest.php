<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De tweede laag naast de scope: ook mét een geldig ID mag je niets
 * van een andere school, en binnen je eigen school geldt je rol.
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    protected function gebruiker(School $school, Role $rol): User
    {
        $user = User::factory()->for($school)->create();
        $user->assignRole($rol->value);

        return $user;
    }

    public function test_een_eigenaar_mag_geen_speler_van_een_andere_school_zien(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $eigenaarA = $this->gebruiker($schoolA, Role::Eigenaar);
        $spelerB = Player::factory()->for($schoolB)->create();

        $this->assertFalse($eigenaarA->can('view', $spelerB));
    }

    public function test_een_trainer_ziet_de_spelers_van_de_eigen_school(): void
    {
        $school = School::factory()->create();
        $trainer = $this->gebruiker($school, Role::Trainer);
        $speler = Player::factory()->for($school)->create();

        $this->assertTrue($trainer->can('view', $speler));
    }

    public function test_een_trainer_mag_geen_spelers_aanmaken_of_verwijderen(): void
    {
        $school = School::factory()->create();
        $trainer = $this->gebruiker($school, Role::Trainer);
        $speler = Player::factory()->for($school)->create();

        $this->assertFalse($trainer->can('create', Player::class));
        $this->assertFalse($trainer->can('delete', $speler));
    }

    public function test_een_ouder_ziet_alleen_het_eigen_kind(): void
    {
        $school = School::factory()->create();
        $ouder = $this->gebruiker($school, Role::Ouder);

        app(Tenancy::class)->set($school);

        $eigenKind = Player::factory()->for($school)->create();
        $anderKind = Player::factory()->for($school)->create();

        $ouder->children()->attach($eigenKind->id, ['relationship' => 'moeder']);

        $this->assertTrue($ouder->can('view', $eigenKind));
        $this->assertFalse($ouder->can('view', $anderKind));
    }

    public function test_een_speler_ziet_alleen_zichzelf(): void
    {
        $school = School::factory()->create();
        $spelerUser = $this->gebruiker($school, Role::Speler);

        $eigenProfiel = Player::factory()->for($school)->create(['user_id' => $spelerUser->id]);
        $anderProfiel = Player::factory()->for($school)->create();

        $this->assertTrue($spelerUser->can('view', $eigenProfiel));
        $this->assertFalse($spelerUser->can('view', $anderProfiel));
    }
}
