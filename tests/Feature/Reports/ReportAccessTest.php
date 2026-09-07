<?php

namespace Tests\Feature\Reports;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
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

/**
 * Wie mag wat met rapporten en kaarten — inclusief de vraag of school A
 * ergens bij school B kan komen.
 */
class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    protected function gebruiker(School $school, Role $rol): User
    {
        $user = User::factory()->for($school)->create();
        $user->assignRole($rol->value);

        return $user;
    }

    /** @return array<string, int> */
    protected function cijfers(int $score = 8): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $categorie) => [$categorie->value => $score])
            ->all();
    }

    public function test_een_ouder_mag_geen_rapport_invullen(): void
    {
        $school = School::factory()->create();
        $ouder = $this->gebruiker($school, Role::Ouder);
        $speler = Player::factory()->for($school)->keeper()->create();

        $this->actingAs($ouder)
            ->post("/players/{$speler->id}/reports", ['scores' => $this->cijfers()])
            ->assertForbidden();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_een_ouder_ziet_de_kaart_van_het_eigen_kind_maar_niet_die_van_een_ander(): void
    {
        $school = School::factory()->create();
        $ouder = $this->gebruiker($school, Role::Ouder);

        app(Tenancy::class)->set($school);

        $eigenKind = Player::factory()->for($school)->keeper()->create();
        $anderKind = Player::factory()->for($school)->keeper()->create();

        $ouder->children()->attach($eigenKind->id, ['relationship' => 'moeder']);

        $this->actingAs($ouder)->get("/players/{$eigenKind->id}/card")->assertOk();
        $this->actingAs($ouder)->get("/players/{$anderKind->id}/card")->assertForbidden();
    }

    public function test_een_ouder_komt_niet_in_de_rapportenlijst(): void
    {
        $school = School::factory()->create();
        $ouder = $this->gebruiker($school, Role::Ouder);

        $this->actingAs($ouder)->get('/reports')->assertForbidden();
    }

    public function test_een_trainer_kan_geen_rapport_schrijven_voor_een_speler_van_een_andere_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $trainerA = $this->gebruiker($schoolA, Role::Trainer);
        $spelerB = Player::factory()->for($schoolB)->keeper()->create();

        // De global scope laat de speler niet eens zien: dat is een 404,
        // nog voordat de policy eraan te pas komt.
        $this->actingAs($trainerA)
            ->post("/players/{$spelerB->id}/reports", ['scores' => $this->cijfers()])
            ->assertNotFound();

        $this->actingAs($trainerA)->get("/players/{$spelerB->id}/card")->assertNotFound();
        $this->actingAs($trainerA)->get("/players/{$spelerB->id}/reports/create")->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_de_rapportenlijst_toont_alleen_spelers_van_de_eigen_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $trainerA = $this->gebruiker($schoolA, Role::Trainer);

        Player::factory()->for($schoolA)->create(['first_name' => 'Speler', 'last_name' => 'Van A']);
        Player::factory()->for($schoolB)->create(['first_name' => 'Speler', 'last_name' => 'Van B']);

        $this->actingAs($trainerA)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Index')
                ->count('players', 1)
                ->where('players.0.name', 'Speler Van A')
            );
    }

    public function test_een_rapport_krijgt_het_school_id_van_de_ingelogde_trainer(): void
    {
        $school = School::factory()->create();
        $trainer = $this->gebruiker($school, Role::Trainer);
        $speler = Player::factory()->for($school)->keeper()->create();

        $this->actingAs($trainer)->post("/players/{$speler->id}/reports", ['scores' => $this->cijfers()]);

        $this->assertDatabaseHas('reports', ['school_id' => $school->id, 'player_id' => $speler->id]);
        $this->assertDatabaseHas('report_scores', ['school_id' => $school->id]);
    }

    public function test_een_speler_ziet_de_eigen_kaart(): void
    {
        $school = School::factory()->create();
        $spelerUser = $this->gebruiker($school, Role::Speler);
        $speler = Player::factory()->for($school)->keeper()->create(['user_id' => $spelerUser->id]);

        $this->actingAs($spelerUser)
            ->get("/players/{$speler->id}/card")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canReport', false));
    }

    public function test_de_rapportenlijst_zegt_hoe_lang_geleden_iemand_beoordeeld_is(): void
    {
        $school = School::factory()->create();
        $trainer = $this->gebruiker($school, Role::Trainer);

        app(Tenancy::class)->set($school);

        $recent = Player::factory()->for($school)->keeper()->create(['first_name' => 'Aap']);
        $lang = Player::factory()->for($school)->keeper()->create(['first_name' => 'Beer']);
        Player::factory()->for($school)->keeper()->create(['first_name' => 'Cees']);

        Report::factory()->for($school)->create([
            'player_id' => $recent->id,
            'reported_on' => now()->subDays(3),
        ]);

        Report::factory()->for($school)->create([
            'player_id' => $lang->id,
            'reported_on' => now()->subDays(45),
        ]);

        $this->actingAs($trainer)
            ->get('/reports')
            ->assertInertia(fn ($page) => $page
                ->where('players.0.days_since_report', 3)
                ->where('players.1.days_since_report', 45)
                // Nooit beoordeeld is geen nul dagen geleden.
                ->where('players.2.days_since_report', null)
                ->where('staleAfterDays', 30)
            );
    }

    public function test_de_rapportenlijst_kan_op_groep_filteren(): void
    {
        $school = School::factory()->create();
        $trainer = $this->gebruiker($school, Role::Trainer);

        app(Tenancy::class)->set($school);

        $groep = Group::factory()->for($school)->create(['name' => 'Keepers ochtend']);

        $inDeGroep = Player::factory()->for($school)->create(['first_name' => 'Sem']);
        $inDeGroep->groups()->attach($groep->id);

        Player::factory()->for($school)->create(['first_name' => 'Daan']);

        // Vanuit een training kom je hier met die groep in de URL.
        $this->actingAs($trainer)
            ->get('/reports?group='.$groep->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('players', 1)
                ->where('players.0.name', $inDeGroep->full_name)
                ->where('group', 'Keepers ochtend')
            );

        $this->actingAs($trainer)
            ->get('/reports')
            ->assertInertia(fn ($page) => $page->count('players', 2)->where('group', null));
    }
}
