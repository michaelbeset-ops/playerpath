<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function eigenaar(School $school): User
    {
        $user = User::factory()->for($school)->create();
        $user->assignRole(Role::Eigenaar->value);

        return $user;
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->eigenaar($school))->get('/dashboard')->assertOk();
    }

    public function test_het_dashboard_telt_alleen_de_eigen_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        Player::factory()->count(3)->for($schoolA)->create();
        Player::factory()->count(7)->for($schoolB)->create();

        $this->actingAs($this->eigenaar($schoolA))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('view', 'school')
                ->where('stats.players', 3)
                ->where('stats.reportsThisWeek', 0)
            );
    }

    public function test_inactieve_spelers_tellen_niet_mee(): void
    {
        $school = School::factory()->create();

        Player::factory()->count(2)->for($school)->create();
        Player::factory()->for($school)->create(['is_active' => false]);

        $this->actingAs($this->eigenaar($school))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.players', 2));
    }

    public function test_een_ouder_krijgt_het_eigen_kind_te_zien_en_geen_schoolcijfers(): void
    {
        $school = School::factory()->create();

        $ouder = User::factory()->for($school)->create();
        $ouder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($school);

        $eigenKind = Player::factory()->for($school)->keeper()->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);
        Player::factory()->count(4)->for($school)->create();

        $ouder->children()->attach($eigenKind->id);

        $this->actingAs($ouder)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'gezin')
                ->count('players', 1)
                ->where('players.0.name', 'Sem de Vries')
                ->where('players.0.position_key', 'keeper')
                ->has('players.0.categories', 6)
                ->has('players.0.level')
                ->where('players.0.next_badge.key', 'eerste_rapport')
                // Schoolbrede cijfers horen hier niet: die zijn niet van een ouder.
                ->missing('stats')
            );
    }

    public function test_het_dashboard_wijst_spelers_aan_die_te_lang_geen_rapport_hadden(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $vergeten = Player::factory()->for($school)->keeper()->create(['first_name' => 'Vergeten', 'last_name' => 'Speler']);
        $recent = Player::factory()->for($school)->keeper()->create(['first_name' => 'Recent', 'last_name' => 'Beoordeeld']);

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $vergeten->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subDays(60),
        ]);

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $recent->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subDays(3),
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('needsAttention', 1)
                ->where('needsAttention.0.name', 'Vergeten Speler')
            );
    }

    public function test_een_speler_zonder_enig_rapport_vraagt_ook_om_aandacht(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        Player::factory()->for($school)->create(['first_name' => 'Nooit', 'last_name' => 'Beoordeeld']);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('needsAttention', 1)
                ->where('needsAttention.0.last_report_on', null)
            );
    }

    public function test_de_gemiddelde_rating_telt_alleen_spelers_met_cijfers(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        Player::factory()->for($school)->create()->forceFill(['overall_rating' => 60])->save();
        Player::factory()->for($school)->create()->forceFill(['overall_rating' => 80])->save();
        Player::factory()->for($school)->create(); // nog geen cijfers

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.averageRating', 70));
    }

    public function test_de_opkomst_telt_alleen_wat_echt_is_afgevinkt(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $groep = \App\Models\Group::factory()->for($school)->create();
        $training = \App\Models\Training::factory()->for($school)->for($groep)->past()->create();

        $spelers = Player::factory()->count(4)->for($school)->create();

        // Drie aanwezig, een afwezig, en een vierde die niet is afgevinkt.
        \App\Models\Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[0]->id, 'status' => 'present',
        ]);
        \App\Models\Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[1]->id, 'status' => 'present',
        ]);
        \App\Models\Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[2]->id, 'status' => 'absent',
        ]);
        \App\Models\Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[3]->id, 'status' => null,
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('stats.attendanceRate.percentage', 67)
                ->where('stats.attendanceRate.total', 3)
            );
    }

    public function test_een_trainer_ziet_geen_financieel_overzicht_en_geen_beheeracties(): void
    {
        $school = School::factory()->create();

        $trainer = User::factory()->for($school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'school')
                ->where('can.seeFinance', false)
                ->where('can.managePlayers', false)
                ->where('can.manageGroups', false)
                // Trainingen inplannen mag hij wel.
                ->where('can.planTrainings', true)
            );
    }

    public function test_een_eigenaar_ziet_het_financiele_vak(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->eigenaar($school))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('can.seeFinance', true));
    }

    public function test_de_dashboardcijfers_blijven_binnen_de_eigen_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        app(Tenancy::class)->set($schoolB);
        Player::factory()->count(5)->for($schoolB)->create();
        \App\Models\Group::factory()->for($schoolB)->create();
        \App\Models\Training::factory()->for($schoolB)->for(\App\Models\Group::factory()->for($schoolB))->upcoming()->create();

        app(Tenancy::class)->set($schoolA);
        Player::factory()->count(2)->for($schoolA)->create();

        $this->actingAs($this->eigenaar($schoolA))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('stats.players', 2)
                ->where('stats.groups', 0)
                ->count('upcomingTrainings', 0)
                ->count('needsAttention', 2)
            );
    }

    public function test_rapporten_van_deze_week_worden_geteld(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $speler = Player::factory()->for($school)->keeper()->create();

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now(),
        ]);

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subWeeks(3),
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.reportsThisWeek', 1));
    }
}
