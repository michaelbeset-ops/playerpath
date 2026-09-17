<?php

namespace Tests\Feature\Trainers;

use App\Enums\Role;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Een trainer mag zijn eigen grens niet verschuiven.
 *
 * Welke spelers van hem zijn volgt uit de trainingen waar hij aan gekoppeld
 * is. Mocht hij die koppeling zelf zetten of wissen, dan was de grens een
 * suggestie.
 */
class TrainerScopeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Group $eigenGroep;

    protected Group $andereGroep;

    protected Training $eigenTraining;

    protected Training $andereTraining;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->eigenGroep = Group::factory()->for($this->school)->create();
        $this->andereGroep = Group::factory()->for($this->school)->create();

        $this->eigenTraining = Training::factory()->for($this->school)->for($this->eigenGroep)->create();
        $this->eigenTraining->trainers()->attach($this->trainer->id);

        $this->andereTraining = Training::factory()->for($this->school)->for($this->andereGroep)->create();
    }

    protected function gegevens(Group $groep, array $extra = []): array
    {
        return [
            'group_id' => $groep->id,
            'date' => now()->addDays(3)->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            ...$extra,
        ];
    }

    public function test_een_trainer_bewerkt_geen_training_van_een_andere_groep(): void
    {
        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->andereTraining->id, $this->gegevens($this->andereGroep, ['trainers' => [$this->trainer->id]]))
            ->assertForbidden();

        $this->assertFalse($this->andereTraining->trainers()->whereKey($this->trainer->id)->exists());
    }

    public function test_een_trainer_kan_zich_niet_ontkoppelen_om_alles_te_zien(): void
    {
        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$this->eigenTraining->id, $this->gegevens($this->eigenGroep, ['trainers' => []]))
            ->assertRedirect();

        $this->assertTrue($this->eigenTraining->trainers()->whereKey($this->trainer->id)->exists());
    }

    public function test_een_trainer_plant_niet_in_voor_een_andere_groep(): void
    {
        $this->actingAs($this->trainer)
            ->post('/trainings', $this->gegevens($this->andereGroep))
            ->assertSessionHasErrors('group_id');

        $this->assertSame(1, $this->andereGroep->trainings()->count());
    }

    public function test_een_nieuwe_training_van_een_trainer_krijgt_hemzelf_als_trainer(): void
    {
        $collega = User::factory()->for($this->school)->create();
        $collega->assignRole(Role::Trainer->value);

        $this->actingAs($this->trainer)
            ->post('/trainings', $this->gegevens($this->eigenGroep, ['trainers' => [$collega->id]]))
            ->assertRedirect();

        $nieuw = Training::query()->latest('id')->first();
        $this->assertSame([$this->trainer->id], $nieuw->trainers()->pluck('users.id')->all());
    }

    public function test_de_eigenaar_kiest_de_trainers_wel_vrij(): void
    {
        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->patch('/trainings/'.$this->andereTraining->id, $this->gegevens($this->andereGroep, ['trainers' => [$this->trainer->id]]))
            ->assertRedirect();

        $this->assertTrue($this->andereTraining->trainers()->whereKey($this->trainer->id)->exists());
    }

    public function test_een_trainer_zet_geen_doel_bij_een_speler_die_niet_van_hem_is(): void
    {
        $vreemd = Player::factory()->for($this->school)->create();
        $vreemd->groups()->attach($this->andereGroep->id);

        $doel = Goal::factory()->for($this->school)->create(['player_id' => $vreemd->id]);

        $this->assertFalse($this->trainer->can('createFor', [Goal::class, $vreemd]));
        $this->assertFalse($this->trainer->can('update', $doel));
        $this->assertFalse($this->trainer->can('delete', $doel));

        $eigen = Player::factory()->for($this->school)->create();
        $eigen->groups()->attach($this->eigenGroep->id);

        $this->assertTrue($this->trainer->can('createFor', [Goal::class, $eigen]));
    }
}
