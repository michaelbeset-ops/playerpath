<?php

namespace Tests\Feature\Progress;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Goals\GoalProgress;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\PlayerCard\PlayerTimeline;
use App\Support\Progress\NextStep;
use App\Support\Rating\RatingEngine;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Het voortgangsscherm: het volgende doel, de trendwoorden en de tijdlijn.
 */
class NextStepTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->speler = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Sem']);
    }

    /** @param  array<string, float>  $afwijkend */
    protected function rapporteer(float $cijfer, array $afwijkend = []): void
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $afwijkend[$c->value] ?? $cijfer])
            ->all();

        $this->actingAs($this->trainer)
            ->post("/players/{$this->speler->id}/reports", ['scores' => $scores])
            ->assertSessionHasNoErrors();
    }

    public function test_zonder_doel_wijst_het_de_laagste_categorie_aan(): void
    {
        $this->rapporteer(8, [ReportCategory::Communicatie->value => 5]);

        $stap = app(NextStep::class)->for($this->speler->refresh(), []);

        $this->assertSame('suggestion', $stap['type']);
        $this->assertSame('communicatie', $stap['category']);
        $this->assertSame(50, $stap['from']);
        // Vijf punten hoger: klein genoeg om te halen.
        $this->assertSame(55, $stap['to']);
    }

    public function test_een_lopend_doel_is_het_volgende_doel(): void
    {
        $this->rapporteer(7);

        $this->actingAs($this->trainer)->post("/players/{$this->speler->id}/goals", [
            'category' => ReportCategory::Reflexen->value,
            'target' => 9,
            'due_on' => now()->addMonth()->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $speler = $this->speler->refresh();
        $stap = app(NextStep::class)->for($speler, app(GoalProgress::class)->forPlayer($speler));

        $this->assertSame('goal', $stap['type']);
        $this->assertSame('reflexen', $stap['category']);
        $this->assertSame(90, $stap['to']);
    }

    public function test_zonder_cijfers_is_er_geen_voorstel(): void
    {
        $this->assertNull(app(NextStep::class)->for($this->speler, []));
    }

    public function test_het_verloop_krijgt_een_woord(): void
    {
        $this->assertSame('sterk', PlayerProgress::trend(7)['key']);
        $this->assertSame('groei', PlayerProgress::trend(2)['key']);
        $this->assertSame('stabiel', PlayerProgress::trend(0)['key']);
        $this->assertSame('stabiel', PlayerProgress::trend(-1)['key']);
        $this->assertSame('aandacht', PlayerProgress::trend(-4)['key']);
        // Eén meetpunt is geen verloop.
        $this->assertNull(PlayerProgress::trend(null));
    }

    public function test_de_tijdlijn_toont_wanneer_een_level_gehaald_is(): void
    {
        $engine = app(RatingEngine::class);
        $engine->award($this->speler, 'attendance', 160, 'Trainingen');

        $tijdlijn = app(PlayerTimeline::class)->for($this->speler->refresh());
        $levels = array_values(array_filter($tijdlijn, fn (array $item) => $item['type'] === 'level'));

        $this->assertCount(1, $levels);
        $this->assertSame('Level omhoog: Zilver', $levels[0]['title']);
    }

    public function test_het_voortgangsscherm_levert_alles_wat_het_toont(): void
    {
        $this->rapporteer(6);
        $this->rapporteer(8);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->speler->id}/progress")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('card')
                ->has('level')
                ->has('nextStep')
                ->where('canReport', true)
                ->where('progress.overall.trend.key', 'sterk')
                ->has('progress.categories.0.trend')
            );
    }
}
