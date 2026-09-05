<?php

namespace Tests\Feature\Trainings;

use App\Enums\AttendanceStatus;
use App\Enums\Registration;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Group $groep;

    protected Training $training;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->groep = Group::factory()->for($this->school)->create();
        $this->speler = Player::factory()->for($this->school)->keeper()->create();
        $this->speler->groups()->attach($this->groep->id);

        $this->training = Training::factory()->for($this->school)->for($this->groep)->upcoming()->create();
    }

    protected function ouderVan(Player $speler): User
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $speler->guardians()->attach($ouder->id, ['relationship' => 'moeder']);

        return $ouder;
    }

    public function test_een_trainer_vinkt_aanwezigheid_af(): void
    {
        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$this->speler->id, ['status' => 'present'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'school_id' => $this->school->id,
            'training_id' => $this->training->id,
            'player_id' => $this->speler->id,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function test_nog_een_keer_afvinken_haalt_de_keuze_weer_weg(): void
    {
        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$this->speler->id, ['status' => 'present']);

        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$this->speler->id, ['status' => null]);

        $this->assertDatabaseHas('attendances', [
            'training_id' => $this->training->id,
            'player_id' => $this->speler->id,
            'status' => null,
        ]);
    }

    public function test_een_speler_buiten_de_groep_kan_niet_afgevinkt_worden(): void
    {
        $buitenstaander = Player::factory()->for($this->school)->create();

        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$buitenstaander->id, ['status' => 'present'])
            ->assertNotFound();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_een_ouder_mag_geen_aanwezigheid_afvinken(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $this->actingAs($ouder)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$this->speler->id, ['status' => 'present'])
            ->assertForbidden();
    }

    public function test_een_ouder_meldt_zijn_kind_aan_en_af(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $this->actingAs($ouder)
            ->post('/trainings/'.$this->training->id.'/registration/'.$this->speler->id, ['registration' => 'attending'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'training_id' => $this->training->id,
            'player_id' => $this->speler->id,
            'registration' => Registration::Attending->value,
            'registered_by_id' => $ouder->id,
        ]);

        $this->actingAs($ouder)
            ->post('/trainings/'.$this->training->id.'/registration/'.$this->speler->id, ['registration' => 'declined']);

        $this->assertDatabaseHas('attendances', [
            'player_id' => $this->speler->id,
            'registration' => Registration::Declined->value,
        ]);
    }

    public function test_aanmelden_raakt_de_afvink_status_niet(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->training->id.'/attendance/'.$this->speler->id, ['status' => 'present']);

        $this->actingAs($ouder)
            ->post('/trainings/'.$this->training->id.'/registration/'.$this->speler->id, ['registration' => 'declined']);

        // Afmelden en toch aanwezig zijn geweest: allebei blijven staan.
        $this->assertDatabaseHas('attendances', [
            'player_id' => $this->speler->id,
            'registration' => Registration::Declined->value,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    public function test_een_ouder_kan_niet_het_kind_van_een_ander_aanmelden(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $anderKind = Player::factory()->for($this->school)->create();
        $anderKind->groups()->attach($this->groep->id);

        $this->actingAs($ouder)
            ->post('/trainings/'.$this->training->id.'/registration/'.$anderKind->id, ['registration' => 'attending'])
            ->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_aanmelden_kan_niet_meer_na_afloop(): void
    {
        $ouder = $this->ouderVan($this->speler);
        $geweest = Training::factory()->for($this->school)->for($this->groep)->past()->create();

        $this->actingAs($ouder)
            ->post('/trainings/'.$geweest->id.'/registration/'.$this->speler->id, ['registration' => 'attending'])
            ->assertStatus(422);
    }

    public function test_een_ouder_ziet_alleen_trainingen_van_de_groep_van_zijn_kind(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $andereGroep = Group::factory()->for($this->school)->create();
        $andereTraining = Training::factory()->for($this->school)->for($andereGroep)->upcoming()->create();

        $this->actingAs($ouder)
            ->get('/trainings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 1)
                ->where('upcoming.0.id', $this->training->id)
                ->where('isParticipant', true)
            );

        $this->actingAs($ouder)->get('/trainings/'.$andereTraining->id)->assertForbidden();
        $this->actingAs($ouder)->get('/trainings/'.$this->training->id)->assertOk();
    }

    public function test_een_ouder_ziet_op_de_trainingspagina_alleen_het_eigen_kind(): void
    {
        $ouder = $this->ouderVan($this->speler);

        $anderKind = Player::factory()->for($this->school)->create();
        $anderKind->groups()->attach($this->groep->id);

        $this->actingAs($ouder)
            ->get('/trainings/'.$this->training->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('players', 1)
                ->where('players.0.id', $this->speler->id)
                ->where('can.record', false)
            );
    }

    public function test_een_trainer_ziet_de_hele_groep_op_de_trainingspagina(): void
    {
        Player::factory()->for($this->school)->create()->groups()->attach($this->groep->id);

        $this->actingAs($this->trainer)
            ->get('/trainings/'.$this->training->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('players', 2)->where('can.record', true));
    }
}
