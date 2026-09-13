<?php

namespace Tests\Feature\Trainings;

use App\Enums\Feature;
use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use App\Support\Trainings\ReportPrompts;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De rapport-herinnering rond het einde van een training.
 *
 * Het venster wordt server-side berekend op de eindtijd: tien minuten ervoor
 * tot vijf uur erna. Wat hier echt toe doet is dat het blok vanzelf weggaat -
 * zodra alles is ingevuld, of zodra het venster voorbij is. Een herinnering
 * die blijft staan nadat je hem hebt afgehandeld leer je negeren.
 */
class ReportPromptTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Group $groep;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->groep = Group::factory()->for($this->school)->create();

        $this->travelTo('2026-09-12 18:00:00');
    }

    protected function speler(): Player
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();
        $this->groep->players()->attach($speler->id);

        return $speler;
    }

    /** Een training die op het gegeven moment eindigt. */
    protected function training(string $eindigt, bool $gekoppeld = false): Training
    {
        $training = Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => now()->parse($eindigt)->subHours(1),
            'ends_at' => now()->parse($eindigt),
        ]);

        if ($gekoppeld) {
            $training->trainers()->attach($this->trainer->id);
        }

        return $training;
    }

    protected function rapporteer(Player $speler): void
    {
        $cijfers = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 7])
            ->all();

        $this->actingAs($this->trainer)->post("/players/{$speler->id}/reports", ['scores' => $cijfers]);
    }

    protected function herinneringen(): array
    {
        return app(ReportPrompts::class)->for($this->trainer->fresh());
    }

    public function test_tien_minuten_voor_het_einde_verschijnt_het_blok(): void
    {
        $this->speler();
        $this->training('2026-09-12 18:05:00');

        $this->assertCount(1, $this->herinneringen());
    }

    public function test_ver_voor_het_einde_nog_niet(): void
    {
        $this->speler();
        $this->training('2026-09-12 18:30:00');

        $this->assertSame([], $this->herinneringen());
    }

    public function test_vijf_uur_na_het_einde_is_het_weg(): void
    {
        $this->speler();
        $this->training('2026-09-12 12:30:00');

        $this->assertSame([], $this->herinneringen());
    }

    public function test_binnen_vijf_uur_staat_het_er_nog(): void
    {
        $this->speler();
        $this->training('2026-09-12 14:00:00');

        $this->assertCount(1, $this->herinneringen());
    }

    public function test_het_blok_verdwijnt_zodra_alle_rapporten_binnen_zijn(): void
    {
        $een = $this->speler();
        $twee = $this->speler();
        $this->training('2026-09-12 17:30:00');

        $this->rapporteer($een);

        $blok = $this->herinneringen()[0];
        $this->assertSame(1, $blok['done']);
        $this->assertSame(1, $blok['open']);
        $this->assertTrue(collect($blok['players'])->firstWhere('id', $een->id)['done']);

        $this->rapporteer($twee);

        $this->assertSame([], $this->herinneringen());
    }

    public function test_een_rapport_van_een_andere_dag_telt_niet(): void
    {
        $speler = $this->speler();
        $this->training('2026-09-12 17:30:00');

        // Gisteren ingevuld: dat gaat over een andere training.
        $this->travelTo('2026-09-11 18:00:00');
        $this->rapporteer($speler);
        $this->travelTo('2026-09-12 18:00:00');

        $this->assertSame(1, $this->herinneringen()[0]['open']);
    }

    public function test_meerdere_trainingen_staan_met_de_laatste_bovenaan(): void
    {
        $this->speler();
        $vroeg = $this->training('2026-09-12 15:00:00');
        $laat = $this->training('2026-09-12 17:30:00');

        $ids = array_column($this->herinneringen(), 'id');

        $this->assertSame([$laat->id, $vroeg->id], $ids);
    }

    public function test_een_afgezegde_training_herinnert_niet(): void
    {
        $this->speler();
        $training = $this->training('2026-09-12 17:30:00');
        $training->forceFill(['cancelled_at' => now(), 'cancellation_reason' => 'Onweer'])->save();

        $this->assertSame([], $this->herinneringen());
    }

    public function test_een_training_van_een_andere_trainer_is_niet_van_mij(): void
    {
        $this->speler();
        $ander = User::factory()->for($this->school)->create();
        $ander->assignRole(Role::Trainer->value);

        $this->training('2026-09-12 17:30:00')->trainers()->attach($ander->id);

        $this->assertSame([], $this->herinneringen());
    }

    public function test_zonder_gekoppelde_trainers_is_de_training_van_iedereen(): void
    {
        $this->speler();
        $this->training('2026-09-12 17:30:00');

        // Koppelen is informatief en veel scholen doen het niet; anders zou
        // de herinnering daar nooit iets doen.
        $this->assertCount(1, $this->herinneringen());
    }

    public function test_zonder_de_ontwikkelingslaag_geen_herinnering(): void
    {
        $this->speler();
        $this->training('2026-09-12 17:30:00');
        $this->school->update(['features' => [Feature::Ontwikkeling->value => false]]);

        $this->assertSame([], $this->herinneringen());
    }

    public function test_het_blok_staat_op_het_dashboard_en_bij_mijn_trainingen(): void
    {
        $this->speler();
        $this->training('2026-09-12 17:30:00', gekoppeld: true);

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('reportPrompts', 1));

        $this->actingAs($this->trainer)
            ->get('/trainings/mijn')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainings/Mine')
                ->count('reportPrompts', 1)
            );
    }

    public function test_mijn_trainingen_toont_alleen_de_eigen_en_de_ongekoppelde(): void
    {
        $ander = User::factory()->for($this->school)->create();
        $ander->assignRole(Role::Trainer->value);

        $vanMij = $this->training('2026-09-13 19:00:00', gekoppeld: true);
        $vanNiemand = $this->training('2026-09-14 19:00:00');
        $vanAnder = $this->training('2026-09-15 19:00:00');
        $vanAnder->trainers()->attach($ander->id);

        $this->actingAs($this->trainer)
            ->get('/trainings/mijn')
            ->assertInertia(fn ($page) => $page->where('trainings', function ($rijen) use ($vanMij, $vanNiemand) {
                $this->assertSame([$vanMij->id, $vanNiemand->id], collect($rijen)->pluck('id')->all());

                return true;
            }));
    }

    public function test_een_ouder_komt_niet_bij_mijn_trainingen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($this->speler()->id);

        // Een ouder mag trainingen zien, maar heeft geen "mijn": het menu biedt
        // het niet aan en de herinnering blijft leeg.
        $this->assertSame([], app(ReportPrompts::class)->for($ouder));
    }
}
