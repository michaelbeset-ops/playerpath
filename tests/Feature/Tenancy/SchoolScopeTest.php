<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * De proef op de som van fase 1: school A mag niets van school B zien.
 */
class SchoolScopeTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected User $eigenaarA;

    protected User $eigenaarB;

    protected Player $spelerA;

    protected Player $spelerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->schoolA = School::factory()->create(['name' => 'School A']);
        $this->schoolB = School::factory()->create(['name' => 'School B']);

        $this->eigenaarA = User::factory()->for($this->schoolA)->create();
        $this->eigenaarA->assignRole(Role::Eigenaar->value);

        $this->eigenaarB = User::factory()->for($this->schoolB)->create();
        $this->eigenaarB->assignRole(Role::Eigenaar->value);

        $this->spelerA = Player::factory()->for($this->schoolA)->create(['first_name' => 'Speler', 'last_name' => 'Van A']);
        $this->spelerB = Player::factory()->for($this->schoolB)->create(['first_name' => 'Speler', 'last_name' => 'Van B']);

        Group::factory()->for($this->schoolA)->create(['name' => 'Groep A']);
        Group::factory()->for($this->schoolB)->create(['name' => 'Groep B']);
    }

    protected function alsSchool(School $school): void
    {
        app(Tenancy::class)->set($school);
    }

    public function test_een_school_ziet_alleen_de_eigen_spelers(): void
    {
        $this->alsSchool($this->schoolA);

        $spelers = Player::all();

        $this->assertCount(1, $spelers);
        $this->assertTrue($spelers->first()->is($this->spelerA));
    }

    public function test_een_school_ziet_alleen_de_eigen_groepen(): void
    {
        $this->alsSchool($this->schoolB);

        $groepen = Group::all();

        $this->assertCount(1, $groepen);
        $this->assertSame('Groep B', $groepen->first()->name);
    }

    public function test_een_speler_van_een_andere_school_is_niet_op_id_te_vinden(): void
    {
        $this->alsSchool($this->schoolA);

        $this->assertNull(Player::find($this->spelerB->id));
    }

    public function test_zonder_actieve_school_levert_een_query_niets_op(): void
    {
        app(Tenancy::class)->forget();

        $this->assertCount(0, Player::all());
        $this->assertCount(0, Group::all());
    }

    public function test_opslaan_zonder_actieve_school_gooit_een_fout(): void
    {
        app(Tenancy::class)->forget();

        $this->expectException(RuntimeException::class);

        Player::create([
            'first_name' => 'Zwevende',
            'last_name' => 'Speler',
            'date_of_birth' => '2013-01-01',
            'position' => 'keeper',
        ]);
    }

    public function test_school_id_wordt_automatisch_ingevuld(): void
    {
        $this->alsSchool($this->schoolB);

        $speler = Player::create([
            'first_name' => 'Nieuwe',
            'last_name' => 'Speler',
            'date_of_birth' => '2013-01-01',
            'position' => 'keeper',
        ]);

        $this->assertSame($this->schoolB->id, $speler->school_id);
    }

    public function test_school_id_is_niet_massaal_toe_te_wijzen(): void
    {
        $this->alsSchool($this->schoolA);

        $this->spelerA->update(['school_id' => $this->schoolB->id]);

        $this->assertSame($this->schoolA->id, $this->spelerA->fresh()->school_id);
    }

    public function test_de_school_van_een_bestaande_speler_kan_niet_worden_gewijzigd(): void
    {
        $this->alsSchool($this->schoolA);

        $this->expectException(RuntimeException::class);

        $this->spelerA->school_id = $this->schoolB->id;
        $this->spelerA->save();
    }

    public function test_een_speler_kan_niet_in_een_groep_van_een_andere_school(): void
    {
        $this->alsSchool($this->schoolA);

        $groepVanB = Group::withoutSchoolScope()->where('name', 'Groep B')->firstOrFail();

        $this->spelerA->groups()->attach($groepVanB->id);

        // De koppeling krijgt het school_id van de speler, dus vanuit school B
        // is de groep nog steeds leeg.
        $this->alsSchool($this->schoolB);
        $this->assertCount(0, $groepVanB->fresh()->players);
    }
}
