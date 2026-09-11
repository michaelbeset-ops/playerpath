<?php

namespace Tests\Feature\Goals;

use App\Enums\GoalStatus;
use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Goal;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\DoelBehaald;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Goals\GoalProgress;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Player $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();
    }

    /** @return array<string, int> */
    protected function cijfers(int $score): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $score])
            ->all();
    }

    protected function rapporteer(int $score): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers($score)]);
    }

    /**
     * Een streefcijfer heeft een decimaal, zoals een rapportcijfer: 6,7 is
     * een ander doel dan 7. Op de kaart is dat 67, dus er gaat niets verloren.
     */
    public function test_een_streefcijfer_mag_een_komma_hebben(): void
    {
        $this->rapporteer(6);

        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => '6,7', 'due_on' => now()->addMonths(2)->toDateString()])
            ->assertSessionHasNoErrors();

        $doel = Goal::active()->firstOrFail();

        $this->assertSame(67, $doel->target_rating);
        $this->assertSame('6,7', $doel->targetGrade());
        $this->assertSame('Reflexen naar 6,7', $doel->describe());

        $beeld = app(GoalProgress::class)->describe($doel, $this->keeper->refresh());
        $this->assertSame('6,7', $beeld['target_grade']);
        $this->assertSame('6,0', $beeld['current_grade']);
        $this->assertSame('on_track', $beeld['track']);

        // Ook met een punt, en een eigen doel mag een richtpunt dragen.
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => Goal::CUSTOM, 'custom_label' => 'Uitverdedigen', 'target' => '7.5', 'due_on' => now()->addMonth()->toDateString()])
            ->assertSessionHasNoErrors();

        $eigen = Goal::where('category', Goal::CUSTOM)->firstOrFail();
        $this->assertSame(75, $eigen->target_rating);
        $this->assertNull(app(GoalProgress::class)->describe($eigen, $this->keeper)['progress']);

        // Onzin wordt geweigerd.
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'uitkomen', 'target' => '11', 'due_on' => now()->addMonth()->toDateString()])
            ->assertSessionHasErrors('target');
    }

    public function test_een_trainer_stelt_een_doel_met_het_huidige_cijfer_als_start(): void
    {
        $this->rapporteer(6);

        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", [
                'category' => 'reflexen',
                'target' => 8,
                'due_on' => now()->addMonths(2)->toDateString(),
                'note' => 'Eerste reactie.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('goals', [
            'school_id' => $this->school->id,
            'player_id' => $this->keeper->id,
            'category' => 'reflexen',
            'start_rating' => 60,
            'target_rating' => 80,
            'status' => 'active',
        ]);
    }

    public function test_een_streefcijfer_onder_het_huidige_wordt_geweigerd(): void
    {
        $this->rapporteer(8);

        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 7, 'due_on' => now()->addMonth()->toDateString()])
            ->assertSessionHasErrors('target');
    }

    public function test_een_categorie_van_de_verkeerde_positie_wordt_geweigerd(): void
    {
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'afwerking', 'target' => 8, 'due_on' => now()->addMonth()->toDateString()])
            ->assertSessionHasErrors('category');
    }

    public function test_een_nieuw_doel_in_dezelfde_categorie_vervangt_het_oude(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 7, 'due_on' => now()->addMonth()->toDateString()]);
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 9, 'due_on' => now()->addMonths(2)->toDateString()]);

        $this->assertSame(1, Goal::active()->count());
        $this->assertSame(90, Goal::active()->first()->target_rating);
        $this->assertSame(1, Goal::where('status', GoalStatus::Cancelled->value)->count());
    }

    public function test_een_rapport_dat_het_doel_haalt_viert_dat_bij_de_ouders(): void
    {
        Notification::fake();

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->keeper->guardians()->attach($ouder->id);

        $this->rapporteer(6);
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 7, 'due_on' => now()->addMonths(2)->toDateString()]);

        // Gemiddelde van 6 en 8 is 7 -> 70: precies het doel.
        $this->rapporteer(8);

        $goal = Goal::firstOrFail();
        $this->assertSame(GoalStatus::Achieved, $goal->status);
        $this->assertNotNull($goal->achieved_at);

        Notification::assertSentTo($ouder, DoelBehaald::class);
    }

    public function test_een_verlopen_doel_wordt_niet_gehaald(): void
    {
        $this->rapporteer(5);

        Goal::factory()->for($this->school)->create([
            'player_id' => $this->keeper->id,
            'category' => ReportCategory::Reflexen,
            'start_rating' => 50,
            'target_rating' => 90,
            'starts_on' => now()->subMonths(3)->toDateString(),
            'due_on' => now()->subDay()->toDateString(),
        ]);

        $this->rapporteer(6);

        $this->assertSame(GoalStatus::Missed, Goal::firstOrFail()->status);
    }

    public function test_op_koers_vergelijkt_afgelegde_weg_met_verstreken_tijd(): void
    {
        $this->rapporteer(6);

        // Halverwege de tijd, nog niets gegroeid: achter op schema.
        $goal = Goal::factory()->for($this->school)->create([
            'player_id' => $this->keeper->id,
            'category' => ReportCategory::Reflexen,
            'start_rating' => 60,
            'target_rating' => 80,
            'starts_on' => now()->subDays(30)->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
        ]);

        $beeld = app(GoalProgress::class)->describe($goal, $this->keeper->refresh());

        $this->assertSame(0, $beeld['progress']);
        $this->assertSame(50, $beeld['expected']);
        $this->assertFalse($beeld['on_track']);

        // Na een 8 staat het gemiddelde op 70: halverwege de weg, dus op koers.
        $this->rapporteer(8);
        $beeld = app(GoalProgress::class)->describe($goal->refresh(), $this->keeper->refresh());

        $this->assertSame(50, $beeld['progress']);
        $this->assertTrue($beeld['on_track']);
    }

    public function test_een_gehaald_doel_geeft_een_badge_en_staat_in_de_tijdlijn(): void
    {
        $this->rapporteer(6);
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 7, 'due_on' => now()->addMonths(2)->toDateString()]);
        $this->rapporteer(8);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/card")
            ->assertInertia(fn ($page) => $page
                ->where('badges', fn ($badges) => collect($badges)->firstWhere('key', 'doel_gehaald')['earned'] === true)
                ->where('goals.0.status', 'achieved')
            );

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/progress")
            ->assertInertia(fn ($page) => $page
                ->where('timeline', fn ($items) => collect($items)->contains(fn ($i) => str_starts_with($i['title'], 'Doel gehaald')))
            );
    }

    public function test_het_rapportscherm_toont_het_doel_per_categorie(): void
    {
        $this->rapporteer(6);
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", ['category' => 'uitkomen', 'target' => 8, 'due_on' => now()->addMonths(2)->toDateString()]);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/reports/create")
            ->assertInertia(fn ($page) => $page->where('goals.uitkomen.target', 80));
    }

    public function test_de_spelerspagina_biedt_de_trainer_de_knop_om_een_doel_te_stellen(): void
    {
        // Regressie: de klassenaam werd hier ooit zonder import geschreven,
        // waardoor de policy nooit aansloeg en de knop stilletjes wegbleef.
        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Zes categorieën van zijn positie, plus "Overig (zelf invullen)".
                ->where('can.goals', true)
                ->has('goalCategories', 7)
                ->where('goalCategories.6.value', 'overig'));
    }

    public function test_een_ouder_ziet_het_doel_maar_mag_er_geen_stellen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->keeper->guardians()->attach($ouder->id);

        Goal::factory()->for($this->school)->create(['player_id' => $this->keeper->id]);

        // Doelen staan bij de kaart, niet op het dashboard: daar komt een ouder
        // voor de training en de rekening.
        $this->actingAs($ouder)->get("/players/{$this->keeper->id}/card")->assertInertia(fn ($page) => $page->count('goals', 1));
        $this->actingAs($ouder)->get('/dashboard')->assertInertia(fn ($page) => $page->count('children', 1));

        $this->actingAs($ouder)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => 9, 'due_on' => now()->addMonth()->toDateString()])
            ->assertForbidden();
    }

    public function test_een_doel_van_een_andere_school_is_niet_te_stoppen(): void
    {
        $andere = School::factory()->create();
        $vreemde = Player::factory()->for($andere)->create();
        $goal = Goal::factory()->for($andere)->create(['player_id' => $vreemde->id]);

        $this->actingAs($this->trainer)->delete('/goals/'.$goal->id)->assertNotFound();
    }

    public function test_de_eigenaar_ziet_hoeveel_spelers_een_doel_hebben(): void
    {
        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        Goal::factory()->for($this->school)->create(['player_id' => $this->keeper->id]);

        // Het aantal spelers met een doel staat niet meer op het dashboard;
        // de telling zelf klopt nog steeds.
        $this->assertSame(1, app(SchoolDashboard::class)->stats()['playersWithGoal']);
    }

    // --- Een eigen doel: zelf intypen, zelf afvinken ---

    public function test_een_eigen_doel_heeft_geen_streefcijfer(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => Goal::CUSTOM,
            'custom_label' => 'Uitverdedigen met links',
            'due_on' => now()->addMonth()->toDateString(),
            'note' => 'Elke training tien herhalingen.',
        ])->assertSessionHasNoErrors();

        $doel = Goal::firstOrFail();

        $this->assertTrue($doel->isCustom());
        $this->assertNull($doel->target_rating);
        $this->assertSame('Uitverdedigen met links', $doel->label());

        // Zonder cijfer valt er niets over koers te zeggen; dan hoort er ook
        // geen percentage te staan.
        $beeld = app(GoalProgress::class)->describe($doel->refresh(), $this->keeper);

        $this->assertTrue($beeld['is_custom']);
        $this->assertNull($beeld['progress']);
        $this->assertNull($beeld['on_track']);
        $this->assertSame('Uitverdedigen met links', $beeld['label']);
    }

    public function test_een_eigen_doel_zonder_omschrijving_wordt_geweigerd(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => Goal::CUSTOM,
            'due_on' => now()->addMonth()->toDateString(),
        ])->assertSessionHasErrors('custom_label');

        $this->assertDatabaseCount('goals', 0);
    }

    public function test_een_rapport_haalt_een_eigen_doel_nooit_vanzelf(): void
    {
        Notification::fake();

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => Goal::CUSTOM,
            'custom_label' => 'Meer coachen',
            'due_on' => now()->addMonth()->toDateString(),
        ]);

        // Een perfect rapport zegt niets over een doel dat niet over cijfers gaat.
        $this->rapporteer(10);

        $this->assertSame(GoalStatus::Active, Goal::firstOrFail()->status);
    }

    public function test_de_trainer_vinkt_een_eigen_doel_zelf_af(): void
    {
        Notification::fake();

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($this->keeper->id);

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => Goal::CUSTOM,
            'custom_label' => 'Meer coachen',
            'due_on' => now()->addMonth()->toDateString(),
        ]);

        $doel = Goal::firstOrFail();

        $this->actingAs($this->trainer)->post("/goals/{$doel->id}/behaald")->assertRedirect();

        $this->assertSame(GoalStatus::Achieved, $doel->refresh()->status);
        $this->assertNotNull($doel->achieved_at);

        // Hetzelfde bericht als bij een doel dat vanzelf gehaald wordt.
        Notification::assertSentTo($ouder, DoelBehaald::class);
    }

    public function test_een_doel_met_een_cijfer_vink_je_niet_met_de_hand_af(): void
    {
        $this->rapporteer(6);

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => ReportCategory::Reflexen->value,
            'target' => 8,
            'due_on' => now()->addMonth()->toDateString(),
        ]);

        // Dat gaat vanzelf zodra het cijfer er is; met de hand kunnen afvinken
        // zou betekenen dat de kaart en het doel elkaar kunnen tegenspreken.
        $this->actingAs($this->trainer)
            ->post('/goals/'.Goal::firstOrFail()->id.'/behaald')
            ->assertStatus(422);
    }

    public function test_een_ouder_vinkt_geen_doel_af(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($this->keeper->id);

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/goals", [
            'category' => Goal::CUSTOM,
            'custom_label' => 'Meer coachen',
            'due_on' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($ouder)->post('/goals/'.Goal::firstOrFail()->id.'/behaald')->assertForbidden();
    }
}
