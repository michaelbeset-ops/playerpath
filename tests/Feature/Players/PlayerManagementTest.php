<?php

namespace Tests\Feature\Players;

use App\Enums\PlayerPosition;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
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

    /** @return array<string, mixed> */
    protected function gegevens(array $overschrijf = []): array
    {
        return array_merge([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
            'date_of_birth' => '2013-04-12',
            'position' => PlayerPosition::Keeper->value,
            'is_active' => true,
        ], $overschrijf);
    }

    public function test_een_eigenaar_kan_een_speler_toevoegen(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/players', $this->gegevens())
            ->assertRedirect();

        $this->assertDatabaseHas('players', [
            'school_id' => $this->school->id,
            'first_name' => 'Sem',
            'position' => 'keeper',
        ]);
    }

    public function test_een_speler_wordt_meteen_in_de_gekozen_groepen_gezet(): void
    {
        $groep = Group::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/players', $this->gegevens(['groups' => [$groep->id]]));

        $speler = Player::firstOrFail();

        $this->assertTrue($speler->groups->contains($groep));
        $this->assertDatabaseHas('group_player', [
            'school_id' => $this->school->id,
            'group_id' => $groep->id,
            'player_id' => $speler->id,
        ]);
    }

    public function test_een_groep_van_een_andere_school_wordt_geweigerd(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andereSchool)->create();

        $this->actingAs($this->eigenaar)
            ->post('/players', $this->gegevens(['groups' => [$vreemdeGroep->id]]))
            ->assertSessionHasErrors('groups.0');

        $this->assertDatabaseCount('players', 0);
    }

    public function test_een_geboortedatum_in_de_toekomst_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/players', $this->gegevens(['date_of_birth' => now()->addYear()->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_een_eigenaar_kan_een_speler_bewerken(): void
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();

        $this->actingAs($this->eigenaar)
            ->put('/players/'.$speler->id, $this->gegevens([
                'first_name' => 'Aangepast',
                'position' => PlayerPosition::Field->value,
                'is_active' => false,
            ]))
            ->assertRedirect();

        $speler->refresh();

        $this->assertSame('Aangepast', $speler->first_name);
        $this->assertSame(PlayerPosition::Field, $speler->position);
        $this->assertFalse($speler->is_active);
    }

    public function test_een_trainer_mag_geen_spelers_aanmaken_of_bewerken(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($trainer)->post('/players', $this->gegevens())->assertForbidden();
        $this->actingAs($trainer)->get('/players/create')->assertForbidden();
        $this->actingAs($trainer)->put('/players/'.$speler->id, $this->gegevens())->assertForbidden();
        $this->actingAs($trainer)->delete('/players/'.$speler->id)->assertForbidden();
    }

    public function test_een_trainer_mag_het_spelersoverzicht_wel_bekijken(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        Player::factory()->for($this->school)->create();

        $this->actingAs($trainer)
            ->get('/users?type=players')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('users/Index')->where('can.managePlayers', false));
    }

    public function test_het_overzicht_filtert_op_naam_positie_groep_en_status(): void
    {
        $groep = Group::factory()->for($this->school)->create();

        $keeper = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);
        $keeper->groups()->attach($groep->id);

        Player::factory()->for($this->school)->veldspeler()->create(['first_name' => 'Daan', 'last_name' => 'Visser']);
        Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Oud', 'last_name' => 'Lid', 'is_active' => false]);

        $this->actingAs($this->eigenaar)->get('/users?type=players')
            ->assertInertia(fn ($page) => $page->count('players', 2));

        $this->actingAs($this->eigenaar)->get('/users?type=players&search=Sem')
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('players.0.name', 'Sem de Vries'));

        $this->actingAs($this->eigenaar)->get('/users?type=players&position=field')
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('players.0.name', 'Daan Visser'));

        $this->actingAs($this->eigenaar)->get('/users?type=players&group='.$groep->id)
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('players.0.name', 'Sem de Vries'));

        $this->actingAs($this->eigenaar)->get('/users?type=players&status=inactive')
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('players.0.name', 'Oud Lid'));

        $this->actingAs($this->eigenaar)->get('/users?type=players&status=all')
            ->assertInertia(fn ($page) => $page->count('players', 3));
    }

    public function test_een_speler_van_een_andere_school_is_niet_te_bereiken(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeSpeler = Player::factory()->for($andereSchool)->create();

        $this->actingAs($this->eigenaar)->get('/players/'.$vreemdeSpeler->id)->assertNotFound();
        $this->actingAs($this->eigenaar)->get('/players/'.$vreemdeSpeler->id.'/edit')->assertNotFound();
        $this->actingAs($this->eigenaar)->put('/players/'.$vreemdeSpeler->id, $this->gegevens())->assertNotFound();
        $this->actingAs($this->eigenaar)->delete('/players/'.$vreemdeSpeler->id)->assertNotFound();
    }

    public function test_het_overzicht_toont_alleen_spelers_van_de_eigen_school(): void
    {
        $andereSchool = School::factory()->create();

        Player::factory()->for($this->school)->create(['first_name' => 'Eigen', 'last_name' => 'Speler']);
        Player::factory()->count(4)->for($andereSchool)->create();

        $this->actingAs($this->eigenaar)
            ->get('/users?type=players')
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('players.0.name', 'Eigen Speler'));
    }

    public function test_een_speler_verwijderen_neemt_de_rapporten_mee(): void
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();

        Report::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $this->eigenaar->id,
        ]);

        $this->actingAs($this->eigenaar)->delete('/players/'.$speler->id)->assertRedirect('/players');

        $this->assertDatabaseCount('players', 0);
        $this->assertDatabaseCount('reports', 0);
    }
}
