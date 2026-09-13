<?php

namespace Tests\Feature\Reports;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De snelle invulflow: alle spelers van een training achter elkaar.
 *
 * Wat hier echt toe doet: opslaan gaat door naar de volgende zonder tussenstop,
 * overslaan mag, stoppen mag, en aan het eind staat er één samenvatting. Dat is
 * de belofte van dit scherm; alles daarbuiten is opsmuk.
 */
class QuickFlowTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected User $eigenaar;

    protected Group $groep;

    protected Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->groep = Group::factory()->for($this->school)->create();

        $this->travelTo('2026-09-12 19:30:00');

        $this->training = Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => now()->parse('2026-09-12 18:00:00'),
            'ends_at' => now()->parse('2026-09-12 19:00:00'),
        ]);
    }

    protected function speler(string $voornaam): Player
    {
        $speler = Player::factory()->for($this->school)->keeper()->create(['first_name' => $voornaam]);
        $this->groep->players()->attach($speler->id);

        return $speler;
    }

    /** @return array<string, float> */
    protected function cijfers(float $cijfer = 7): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $cijfer])
            ->all();
    }

    public function test_de_flow_begint_bij_de_eerste_speler_zonder_rapport(): void
    {
        $anna = $this->speler('Anna');
        $this->speler('Bram');

        $this->actingAs($this->trainer)
            ->get("/trainings/{$this->training->id}/rapporten")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Quick')
                ->where('player.id', $anna->id)
                ->where('position', 1)
                ->where('total', 2)
                ->count('roster', 2)
            );
    }

    public function test_opslaan_gaat_direct_door_naar_de_volgende_speler(): void
    {
        $anna = $this->speler('Anna');
        $bram = $this->speler('Bram');

        $this->actingAs($this->trainer)
            ->post("/trainings/{$this->training->id}/rapporten/{$anna->id}", ['scores' => $this->cijfers()])
            // Geen tussenscherm: dat is precies waar het ritme sneuvelt.
            ->assertRedirect("/trainings/{$this->training->id}/rapporten?speler={$bram->id}");
    }

    public function test_na_de_laatste_speler_volgt_de_samenvatting(): void
    {
        $anna = $this->speler('Anna');

        $this->actingAs($this->trainer)
            ->post("/trainings/{$this->training->id}/rapporten/{$anna->id}", ['scores' => $this->cijfers()])
            ->assertRedirect("/trainings/{$this->training->id}/rapporten/klaar");

        $this->actingAs($this->trainer)
            ->get("/trainings/{$this->training->id}/rapporten/klaar")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/QuickSummary')
                ->count('results', 1)
                ->where('openCount', 0)
            );
    }

    /**
     * Het rapport krijgt de datum van de training, niet die van vandaag. Wie
     * 's avonds laat afsluit hoort geen rapport van morgen te krijgen - en het
     * is de datum waarop het herinneringsblok "gedaan" telt.
     */
    public function test_het_rapport_krijgt_de_datum_van_de_training(): void
    {
        $anna = $this->speler('Anna');

        $this->travelTo('2026-09-13 00:30:00');

        $this->actingAs($this->trainer)
            ->post("/trainings/{$this->training->id}/rapporten/{$anna->id}", ['scores' => $this->cijfers()]);

        $this->assertSame('2026-09-12', Report::where('player_id', $anna->id)->value('reported_on')->toDateString());
    }

    public function test_een_overgeslagen_speler_blijft_open_staan(): void
    {
        $anna = $this->speler('Anna');
        $bram = $this->speler('Bram');

        // Bram eerst: Anna is overgeslagen en komt daarna weer aan de beurt.
        $this->actingAs($this->trainer)
            ->post("/trainings/{$this->training->id}/rapporten/{$bram->id}", ['scores' => $this->cijfers()])
            ->assertRedirect("/trainings/{$this->training->id}/rapporten?speler={$anna->id}");

        $this->actingAs($this->trainer)
            ->get("/trainings/{$this->training->id}/rapporten")
            ->assertInertia(fn ($page) => $page->where('player.id', $anna->id));
    }

    public function test_tussentijds_stoppen_bewaart_wat_er_al_is(): void
    {
        $anna = $this->speler('Anna');
        $this->speler('Bram');

        $this->actingAs($this->trainer)
            ->post("/trainings/{$this->training->id}/rapporten/{$anna->id}", ['scores' => $this->cijfers()]);

        // Weglopen en later terugkomen: het rapport van Anna staat er nog.
        $this->assertDatabaseCount('reports', 1);

        $this->actingAs($this->trainer)
            ->get("/trainings/{$this->training->id}/rapporten")
            ->assertInertia(fn ($page) => $page
                ->where('doneCount', 1)
                ->where('roster.0.done', true)
            );
    }

    /** De eigenaar is bij een kleine school zelf ook trainer; één flow, niet twee. */
    public function test_de_eigenaar_gebruikt_dezelfde_flow(): void
    {
        $anna = $this->speler('Anna');

        $this->actingAs($this->eigenaar)
            ->get("/trainings/{$this->training->id}/rapporten")
            ->assertOk();

        $this->actingAs($this->eigenaar)
            ->post("/trainings/{$this->training->id}/rapporten/{$anna->id}", ['scores' => $this->cijfers()])
            ->assertRedirect("/trainings/{$this->training->id}/rapporten/klaar");
    }

    public function test_een_ouder_komt_er_niet_in(): void
    {
        $speler = $this->speler('Anna');

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($speler->id);

        $this->actingAs($ouder)->get("/trainings/{$this->training->id}/rapporten")->assertForbidden();
        $this->actingAs($ouder)
            ->post("/trainings/{$this->training->id}/rapporten/{$speler->id}", ['scores' => $this->cijfers()])
            ->assertForbidden();
    }

    public function test_een_trainer_van_een_andere_school_komt_er_niet_bij(): void
    {
        $this->speler('Anna');

        $andere = School::factory()->create();
        $indringer = User::factory()->for($andere)->create();
        $indringer->assignRole(Role::Trainer->value);

        // Twee sloten op dezelfde deur: de global scope maakt de training van
        // een andere school onvindbaar, en de policy weigert hem daarnaast op
        // "zelfde school". Welke van de twee als eerste dichtzit hangt af van
        // het moment waarop de school bekend is; dat het dicht zit niet.
        $this->actingAs($indringer)
            ->get("/trainings/{$this->training->id}/rapporten")
            ->assertForbidden();
    }

    public function test_de_herinnering_wijst_naar_de_flow(): void
    {
        $this->speler('Anna');
        $this->training->trainers()->attach($this->trainer->id);

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('reportPrompts.0.href', "/trainings/{$this->training->id}/rapporten")
            );
    }
}
