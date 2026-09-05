<?php

namespace Tests\Feature\Groups;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    public function test_een_eigenaar_kan_een_groep_aanmaken(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/groups', ['name' => 'Keepers ochtend', 'age_category' => 'Onder 11', 'is_active' => true])
            ->assertRedirect('/groups');

        $this->assertDatabaseHas('groups', [
            'school_id' => $this->school->id,
            'name' => 'Keepers ochtend',
            'age_category' => 'Onder 11',
        ]);
    }

    public function test_twee_groepen_met_dezelfde_naam_mogen_niet_binnen_een_school(): void
    {
        Group::factory()->for($this->school)->create(['name' => 'Selectie']);

        $this->actingAs($this->eigenaar)
            ->post('/groups', ['name' => 'Selectie', 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_een_andere_school_mag_dezelfde_groepsnaam_gebruiken(): void
    {
        $andereSchool = School::factory()->create();
        Group::factory()->for($andereSchool)->create(['name' => 'Selectie']);

        $this->actingAs($this->eigenaar)
            ->post('/groups', ['name' => 'Selectie', 'is_active' => true])
            ->assertSessionHasNoErrors();
    }

    public function test_het_overzicht_telt_de_spelers_per_groep(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $spelers = Player::factory()->count(2)->for($this->school)->create();

        $spelers->each(fn (Player $speler) => $speler->groups()->attach($groep->id));

        $this->actingAs($this->eigenaar)
            ->get('/groups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('groups/Index')
                ->count('groups', 1)
                ->where('groups.0.players_count', 2)
            );
    }

    public function test_een_groep_verwijderen_laat_de_spelers_bestaan(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $speler = Player::factory()->for($this->school)->create();
        $speler->groups()->attach($groep->id);

        $this->actingAs($this->eigenaar)->delete('/groups/'.$groep->id)->assertRedirect('/groups');

        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('group_player', 0);
        $this->assertDatabaseHas('players', ['id' => $speler->id]);
    }

    public function test_een_trainer_mag_groepen_zien_maar_niet_beheren(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $groep = Group::factory()->for($this->school)->create();

        $this->actingAs($trainer)->get('/groups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($trainer)->post('/groups', ['name' => 'Nieuw', 'is_active' => true])->assertForbidden();
        $this->actingAs($trainer)->delete('/groups/'.$groep->id)->assertForbidden();
    }

    public function test_een_groep_van_een_andere_school_is_niet_te_bereiken(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andereSchool)->create();

        $this->actingAs($this->eigenaar)->get('/groups/'.$vreemdeGroep->id.'/edit')->assertNotFound();
        $this->actingAs($this->eigenaar)->delete('/groups/'.$vreemdeGroep->id)->assertNotFound();
    }

    public function test_een_ouder_komt_niet_bij_het_groepenoverzicht(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)->get('/groups')->assertForbidden();
    }
}
