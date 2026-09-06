<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Plan;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
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
        // Verse school: drie stappen open.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.done', 0)->has('checklist.steps', 3));

        $speler = Player::factory()->for($this->school)->keeper()->create();
        Plan::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.done', 2));

        $this->rapporteer($speler, 7);

        // Alles gedaan: weg ermee, en hij komt nooit terug.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist', null));
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
