<?php

namespace Tests\Feature\Trainings;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
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
        $this->trainer = User::factory()->for($this->school)->create(['name' => 'Piet Trainer']);
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->groep = Group::factory()->for($this->school)->create(['name' => 'Keepers ochtend']);
    }

    protected function training(string $moment, ?string $locatie = 'Veld 3'): Training
    {
        $start = now()->parse($moment)->setTime(18, 0);

        return Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
            'location' => $locatie,
        ]);
    }

    public function test_de_maandweergave_bevat_de_trainingen_van_die_maand_met_trainer_en_locatie(): void
    {
        $training = $this->training('2026-09-15');
        $training->trainers()->attach($this->trainer->id);

        $this->training('2026-11-15'); // buiten beeld

        $this->actingAs($this->trainer)
            ->get('/calendar?view=month&date=2026-09-10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('calendar/Index')
                ->where('view', 'month')
                ->where('title', 'September 2026')
                ->count('trainings', 1)
                ->where('trainings.0.date', '2026-09-15')
                ->where('trainings.0.starts_at', '18:00')
                ->where('trainings.0.group', 'Keepers ochtend')
                ->where('trainings.0.location', 'Veld 3')
                ->where('trainings.0.trainers.0', 'Piet Trainer')
            );
    }

    public function test_het_maandbereik_loopt_van_maandag_tot_en_met_zondag(): void
    {
        // September 2026 begint op een dinsdag en eindigt op een woensdag.
        $this->actingAs($this->trainer)
            ->get('/calendar?view=month&date=2026-09-01')
            ->assertInertia(fn ($page) => $page
                ->where('range.from', '2026-08-31')
                ->where('range.to', '2026-10-04')
            );
    }

    public function test_de_weekweergave_toont_alleen_die_week(): void
    {
        $this->training('2026-09-16'); // woensdag in week 38
        $this->training('2026-09-23'); // week erna

        $this->actingAs($this->trainer)
            ->get('/calendar?view=week&date=2026-09-14')
            ->assertInertia(fn ($page) => $page
                ->where('view', 'week')
                ->where('range.from', '2026-09-14')
                ->where('range.to', '2026-09-20')
                ->count('trainings', 1)
            );
    }

    public function test_een_ouder_ziet_alleen_de_groep_van_het_eigen_kind(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $kind = Player::factory()->for($this->school)->create();
        $kind->groups()->attach($this->groep->id);
        $ouder->children()->attach($kind->id);

        $andereGroep = Group::factory()->for($this->school)->create();

        $this->training('2026-09-15');

        $start = now()->parse('2026-09-16')->setTime(18, 0);
        Training::factory()->for($this->school)->for($andereGroep)->create([
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
        ]);

        $this->actingAs($ouder)
            ->get('/calendar?view=month&date=2026-09-01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('trainings', 1)
                ->where('canManage', false)
            );
    }

    public function test_een_andere_school_staat_niet_in_de_kalender(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andereSchool)->create();
        $start = now()->parse('2026-09-15')->setTime(18, 0);

        Training::factory()->for($andereSchool)->for($vreemdeGroep)->create([
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
        ]);

        $this->actingAs($this->trainer)
            ->get('/calendar?view=month&date=2026-09-01')
            ->assertInertia(fn ($page) => $page->count('trainings', 0));
    }

    public function test_een_kapotte_datum_valt_terug_op_vandaag(): void
    {
        $this->actingAs($this->trainer)
            ->get('/calendar?date=geen-datum')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('date', now()->toDateString()));
    }
}
