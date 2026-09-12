<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Onboarding\OnboardingState;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);
    }

    protected function rapporteer(Player $speler, int $score): void
    {
        $cijfers = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $score])
            ->all();

        $this->actingAs($this->trainer)->post("/players/{$speler->id}/reports", ['scores' => $cijfers]);
    }

    public function test_het_overzicht_telt_spelers_rapporten_en_dekking(): void
    {
        $gevolgd = Player::factory()->for($this->school)->keeper()->create();
        Player::factory()->for($this->school)->keeper()->create();

        $this->rapporteer($gevolgd, 6);

        $this->actingAs($this->eigenaar)
            ->get('/verantwoording')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.players', 2)
                ->where('report.reports', 1)
                ->where('report.playersWithReport', 1)
                // Eén van de twee spelers is gevolgd: dat is het cijfer dat telt.
                ->where('report.coverage', 50)
            );
    }

    public function test_ontwikkeling_wordt_alleen_gemeten_bij_minstens_twee_rapporten(): void
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();

        $this->rapporteer($speler, 6);

        $this->actingAs($this->eigenaar)
            ->get('/verantwoording')
            ->assertInertia(fn ($page) => $page
                ->where('report.development.average', null)
                ->where('report.development.measured', 0)
            );

        $this->rapporteer($speler, 8);

        $this->actingAs($this->eigenaar)
            ->get('/verantwoording')
            ->assertInertia(fn ($page) => $page
                ->where('report.development.average', 20)
                ->where('report.development.measured', 1)
                ->where('report.development.improved', 1)
            );
    }

    public function test_opkomst_blijft_leeg_zolang_er_niets_is_afgevinkt(): void
    {
        Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/verantwoording')
            ->assertInertia(fn ($page) => $page
                ->where('report.attendance.percentage', null)
                ->where('report.attendance.recorded', 0)
            );
    }

    public function test_een_periode_is_te_kiezen_en_filtert_echt(): void
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();
        $this->rapporteer($speler, 6);

        // Een venster dat vóór het rapport ligt, moet leeg zijn.
        $this->actingAs($this->eigenaar)
            ->get('/verantwoording?from=2020-01-01&to=2020-12-31')
            ->assertInertia(fn ($page) => $page->where('report.reports', 0));
    }

    public function test_een_omgekeerde_periode_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/verantwoording?from=2026-06-01&to=2026-01-01')
            ->assertSessionHasErrors('to');
    }

    public function test_een_trainer_verantwoordt_niet_namens_de_school(): void
    {
        $this->actingAs($this->trainer)->get('/verantwoording')->assertForbidden();
    }

    public function test_gegevens_van_een_andere_school_tellen_niet_mee(): void
    {
        $andere = School::factory()->create();
        Player::factory()->count(3)->for($andere)->create();

        Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/verantwoording')
            ->assertInertia(fn ($page) => $page->where('report.players', 1));
    }

    public function test_de_checklist_verdwijnt_zodra_de_school_draait(): void
    {
        // Verse school: vijf praktische stappen open. Wat in de wizard zit
        // (schoolgegevens, groepen, trainers) staat hier niet nog eens.
        // De lijst komt pas na de rondleiding.
        OnboardingState::mark($this->school, 'tour_seen_at');

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('checklist.steps', 8)->where('checklist.done', 0));

        $speler = Player::factory()->for($this->school)->keeper()->create();
        Training::factory()->for($this->school)->create();
        Product::factory()->for($this->school)->create();

        // Speler, training, aanbod. De schoolgegevens, de locatie en de
        // groep staan nog open.
        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.done', 3));

        $this->rapporteer($speler, 7);
        $this->ouder();
        Location::create(['name' => 'Sportpark Noord']);
        Group::factory()->for($this->school)->create();
        OnboardingState::save($this->school->fresh(), ['wizard_step' => 2]);
        app(Tenancy::class)->set($this->school->fresh());

        // Alles gedaan: dan staat de felicitatie er, en pas als die gezien is
        // gaat het blok voorgoed weg.
        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.complete', true)->where('checklist.done', 8));

        $this->actingAs($this->eigenaar->fresh())->post('/onboarding/startlijst/klaar');

        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist', null));
    }

    /** Een ouder met een gekoppeld kind; de laatste stap van de startlijst. */
    protected function ouder(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
    }

    public function test_een_trainer_krijgt_geen_checklist(): void
    {
        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist', null));
    }

    public function test_het_menu_toont_verantwoording_alleen_aan_de_eigenaar(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => in_array('/verantwoording', $this->navHrefs($nav), true)));

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => ! in_array('/verantwoording', $this->navHrefs($nav), true)));
    }
}
