<?php

namespace Tests\Feature;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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
            ->assertInertia(function ($page) {
                $tegels = $page->toArray()['props']['widgets'];

                $page->component('Dashboard')->where('view', 'school');

                $this->assertSame(3, $tegels['kpi_players']['value']);
                $this->assertSame(0, $tegels['kpi_reports']['value']);
            });
    }

    public function test_inactieve_spelers_tellen_niet_mee(): void
    {
        $school = School::factory()->create();

        Player::factory()->count(2)->for($school)->create();
        Player::factory()->for($school)->create(['is_active' => false]);

        $this->actingAs($this->eigenaar($school))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $this->assertSame(2, $this->widget($page->toArray()['props'], 'kpi_players')['value']));
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
                // Alleen het eigen kind, ook al staan er vijf op de school.
                ->count('children', 1)
                ->where('children.0.name', 'Sem de Vries')
                ->has('children.0.level')
                // De kaart zelf zit één tik verderop, niet op het dashboard:
                // een ouder komt hier voor de praktische dingen.
                ->missing('children.0.card')
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

        Report::factory()->for($school)->create([
            'player_id' => $vergeten->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subDays(60),
        ]);

        // Vandaag en niet "drie dagen geleden": op een maandag valt dat in de
        // vorige week, en dan meldt het blok ook de trainer zonder rapport.
        Report::factory()->for($school)->create([
            'player_id' => $recent->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now(),
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('attention', 1)
                ->where('attention.0.key', 'silent_players')
                ->where('attention.0.title', fn ($titel) => str_contains($titel, 'Vergeten Speler'))
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
                ->count('attention', 1)
                ->where('attention.0.key', 'silent_players')
            );
    }

    public function test_de_gemiddelde_rating_komt_uit_de_rapporten(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        // Bewust niet uit players.overall_rating: dat veld heeft geen historie,
        // dus daarmee valt geen "vorige maand" te maken. Zie DashboardTrends.
        $this->rapporteer(Player::factory()->for($school)->keeper()->create(), $eigenaar, 6);
        $this->rapporteer(Player::factory()->for($school)->keeper()->create(), $eigenaar, 8);
        Player::factory()->for($school)->keeper()->create(); // nog geen rapport

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $this->assertSame(70, $this->widget($page->toArray()['props'], 'kpi_rating')['value']));
    }

    /**
     * Een rapport met overal hetzelfde cijfer, via het echte scherm.
     *
     * Niet met factories: die zetten hun eigen school_id, en dan valt het
     * rapport buiten de scope van de school waar de test over gaat.
     */
    protected function rapporteer(Player $speler, User $trainer, int $cijfer): void
    {
        $cijfers = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn ($categorie) => [$categorie->value => $cijfer])
            ->all();

        $this->actingAs($trainer)->post("/players/{$speler->id}/reports", ['scores' => $cijfers]);
    }

    public function test_de_opkomst_telt_alleen_wat_echt_is_afgevinkt(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $groep = Group::factory()->for($school)->create();
        $training = Training::factory()->for($school)->for($groep)->past()->create();

        $spelers = Player::factory()->count(4)->for($school)->create();

        // Drie aanwezig, een afwezig, en een vierde die niet is afgevinkt.
        Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[0]->id, 'status' => 'present',
        ]);
        Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[1]->id, 'status' => 'present',
        ]);
        Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[2]->id, 'status' => 'absent',
        ]);
        Attendance::factory()->for($school)->create([
            'training_id' => $training->id, 'player_id' => $spelers[3]->id, 'status' => null,
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertOk();

        // Twee van de drie afgevinkt aanwezig; de niet-afgevinkte speler telt
        // niet mee, want "niet afgevinkt" is geen "afwezig". De opkomst staat
        // sinds de nieuwe indeling niet meer als kerncijfer op het dashboard,
        // maar de berekening klopt nog steeds.
        $this->assertSame(67, app(SchoolDashboard::class)->stats()['attendanceRate']['percentage']);
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
                // Het financiele vak is van de eigenaar; een trainer krijgt
                // het niet eens aangeboden, dus het wordt ook niet berekend.
                ->where('layout', fn ($layout) => ! collect($layout)->pluck('key')->contains('finance'))
                ->where('widgets', fn ($widgets) => ($widgets['finance'] ?? null) === null)
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
            ->assertInertia(fn ($page) => $page
                ->where('layout', fn ($layout) => collect($layout)->pluck('key')->contains('finance'))
                ->where('widgets', fn ($widgets) => ($widgets['finance'] ?? null) !== null)
            );
    }

    public function test_de_dashboardcijfers_blijven_binnen_de_eigen_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        app(Tenancy::class)->set($schoolB);
        Player::factory()->count(5)->for($schoolB)->create();
        Group::factory()->for($schoolB)->create();
        Training::factory()->for($schoolB)->for(Group::factory()->for($schoolB))->upcoming()->create();

        app(Tenancy::class)->set($schoolA);
        Player::factory()->count(2)->for($schoolA)->create();

        $this->actingAs($this->eigenaar($schoolA))
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertSame([], $props['widgets']['trainings']);
                $this->assertSame(2, $props['widgets']['kpi_players']['value']);
            });
    }

    public function test_rapporten_van_deze_week_worden_geteld(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $speler = Player::factory()->for($school)->keeper()->create();

        Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now(),
        ]);

        Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subWeeks(3),
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $this->assertSame(1, $this->widget($page->toArray()['props'], 'kpi_reports')['value']));
    }
}
