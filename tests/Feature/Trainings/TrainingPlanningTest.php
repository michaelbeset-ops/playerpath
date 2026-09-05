<?php

namespace Tests\Feature\Trainings;

use App\Enums\Role;
use App\Models\Group;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
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

        $this->seed(\Database\Seeders\RoleSeeder::class);

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
}
