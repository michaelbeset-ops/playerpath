<?php

namespace Tests\Feature\Trainings;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Group $groep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->groep = Group::factory()->for($this->school)->create(['name' => 'Keepers ochtend']);
    }

    /** @return array<string, mixed> */
    protected function gegevens(array $overschrijf = []): array
    {
        return array_merge([
            'group_id' => $this->groep->id,
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:30',
            'location' => 'Sportpark De Vliert',
        ], $overschrijf);
    }

    public function test_een_trainer_kan_een_training_inplannen(): void
    {
        $this->actingAs($this->trainer)
            ->post('/trainings', $this->gegevens())
            ->assertRedirect('/trainings');

        $this->assertDatabaseHas('trainings', [
            'school_id' => $this->school->id,
            'group_id' => $this->groep->id,
            'location' => 'Sportpark De Vliert',
        ]);
    }

    public function test_wekelijks_herhalen_maakt_losse_trainingen(): void
    {
        $this->actingAs($this->trainer)->post('/trainings', $this->gegevens([
            'date' => now()->addWeek()->toDateString(),
            'repeat_until' => now()->addWeeks(4)->toDateString(),
        ]));

        // Week 1 t/m 4: vier losse trainingen.
        $this->assertDatabaseCount('trainings', 4);

        $tijden = Training::orderBy('starts_at')->pluck('starts_at');

        $this->assertSame('18:00', $tijden->first()->format('H:i'));
        $this->assertSame(7.0, $tijden[0]->diffInDays($tijden[1]));
    }

    public function test_een_eindtijd_voor_de_begintijd_wordt_geweigerd(): void
    {
        $this->actingAs($this->trainer)
            ->post('/trainings', $this->gegevens(['starts_at' => '19:00', 'ends_at' => '18:00']))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_een_groep_van_een_andere_school_wordt_geweigerd(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andereSchool)->create();

        $this->actingAs($this->trainer)
            ->post('/trainings', $this->gegevens(['group_id' => $vreemdeGroep->id]))
            ->assertSessionHasErrors('group_id');

        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_een_ouder_mag_geen_training_inplannen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)->post('/trainings', $this->gegevens())->assertForbidden();
        $this->actingAs($ouder)->get('/trainings/create')->assertForbidden();
    }

    public function test_alleen_de_eigenaar_mag_een_training_verwijderen(): void
    {
        $training = Training::factory()->for($this->school)->for($this->groep)->create();

        $this->actingAs($this->trainer)->delete('/trainings/'.$training->id)->assertForbidden();

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)->delete('/trainings/'.$training->id)->assertRedirect('/trainings');
        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_een_training_van_een_andere_school_is_niet_te_bereiken(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andereSchool)->create();
        $vreemdeTraining = Training::factory()->for($andereSchool)->for($vreemdeGroep)->create();

        $this->actingAs($this->trainer)->get('/trainings/'.$vreemdeTraining->id)->assertNotFound();
        $this->actingAs($this->trainer)->get('/trainings/'.$vreemdeTraining->id.'/edit')->assertNotFound();
    }

    public function test_het_overzicht_scheidt_komend_en_geweest(): void
    {
        Training::factory()->for($this->school)->for($this->groep)->upcoming()->create();
        Training::factory()->for($this->school)->for($this->groep)->past()->count(2)->create();

        $this->actingAs($this->trainer)
            ->get('/trainings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainings/Index')
                ->count('upcoming', 1)
                ->count('past', 2)
                ->where('isParticipant', false)
            );
    }

    public function test_het_overzicht_filtert_op_groep_en_op_trainer(): void
    {
        $andereGroep = Group::factory()->for($this->school)->create(['name' => 'Veldspelers']);
        $andereTrainer = User::factory()->for($this->school)->create();
        $andereTrainer->assignRole(Role::Trainer->value);

        $vanMij = Training::factory()->for($this->school)->for($this->groep)->upcoming()->create();
        $vanMij->trainers()->attach($this->trainer->id);

        $vanEenAnder = Training::factory()->for($this->school)->for($andereGroep)->upcoming()->create();
        $vanEenAnder->trainers()->attach($andereTrainer->id);

        $this->actingAs($this->trainer)
            ->get('/trainings?group='.$this->groep->id)
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 1)
                ->where('upcoming.0.id', $vanMij->id)
                ->where('filters.group', $this->groep->id)
            );

        $this->actingAs($this->trainer)
            ->get('/trainings?trainer='.$andereTrainer->id)
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 1)
                ->where('upcoming.0.id', $vanEenAnder->id)
            );
    }

    public function test_het_overzicht_telt_afgevinkt_en_aanwezig_apart(): void
    {
        $training = Training::factory()->for($this->school)->for($this->groep)->past()->create();

        $aanwezig = Player::factory()->for($this->school)->create();
        $afwezig = Player::factory()->for($this->school)->create();
        $onbekend = Player::factory()->for($this->school)->create();

        foreach ([$aanwezig, $afwezig, $onbekend] as $speler) {
            $speler->groups()->attach($this->groep->id);
        }

        Attendance::factory()->for($this->school)->for($training)->for($aanwezig)
            ->create(['status' => AttendanceStatus::Present]);
        Attendance::factory()->for($this->school)->for($training)->for($afwezig)
            ->create(['status' => AttendanceStatus::Absent]);

        // Niet afgevinkt is geen afwezig: die speler telt in geen van beide.
        $this->actingAs($this->trainer)
            ->get('/trainings')
            ->assertInertia(fn ($page) => $page
                ->where('past.0.recorded_count', 2)
                ->where('past.0.present_count', 1)
                ->where('past.0.expected_count', 3)
            );
    }

    public function test_een_ouder_krijgt_geen_filters_en_geen_afvinkknoppen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $kind = Player::factory()->for($this->school)->create();
        $kind->groups()->attach($this->groep->id);
        $ouder->children()->attach($kind->id);

        Training::factory()->for($this->school)->for($this->groep)->upcoming()->create();

        // Ook met een groep in de URL: een ouder filtert niet, die krijgt zijn
        // eigen groep en niets anders.
        $this->actingAs($ouder)
            ->get('/trainings?group=999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canRecord', false)
                ->where('isParticipant', true)
                ->count('groups', 0)
                ->count('trainers', 0)
                ->count('upcoming', 1)
            );
    }
}
