<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Dashboard\TrainerDashboard;
use App\Support\Dashboard\WidgetRegistry;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het dashboard van een trainer.
 *
 * Hij is personeel, geen directie: waar moet ik zijn, en wie moet ik nog
 * beoordelen. Geen omzet, geen openstaande rekeningen, geen schoolbrede
 * instellingen - dat is niet alleen "niet nuttig", het is niet van hem.
 */
class TrainerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Group $eigenGroep;

    protected Group $andereGroep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->eigenGroep = Group::factory()->for($this->school)->create(['name' => 'Keepers O12']);
        $this->andereGroep = Group::factory()->for($this->school)->create(['name' => 'Veld O14']);

        $this->travelTo('2026-09-12 09:00:00');
    }

    protected function training(Group $groep, string $begint, ?User $trainer = null): Training
    {
        $training = Training::factory()->for($this->school)->for($groep)->create([
            'starts_at' => now()->parse($begint),
            'ends_at' => now()->parse($begint)->addHour(),
        ]);

        if ($trainer !== null) {
            $training->trainers()->attach($trainer->id);
        }

        return $training;
    }

    protected function speler(Group $groep): Player
    {
        $speler = Player::factory()->for($this->school)->keeper()->create();
        $groep->players()->attach($speler->id);

        return $speler;
    }

    public function test_het_dashboard_toont_zijn_eigen_trainingen(): void
    {
        $ander = User::factory()->for($this->school)->create();
        $ander->assignRole(Role::Trainer->value);

        $vanMij = $this->training($this->eigenGroep, '2026-09-14 19:00:00', $this->trainer);
        $vanAnder = $this->training($this->andereGroep, '2026-09-15 19:00:00', $ander);

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('widgets.my_trainings', function ($rijen) use ($vanMij, $vanAnder) {
                $ids = collect($rijen)->pluck('id');

                $this->assertTrue($ids->contains($vanMij->id));
                $this->assertFalse($ids->contains($vanAnder->id));

                return true;
            }));
    }

    public function test_mijn_spelers_komen_uit_de_groepen_waar_hij_voor_staat(): void
    {
        $this->training($this->eigenGroep, '2026-09-14 19:00:00', $this->trainer);

        $vanMij = $this->speler($this->eigenGroep);
        $vanAnder = $this->speler($this->andereGroep);

        $spelers = app(TrainerDashboard::class)->players($this->trainer)['players'];
        $ids = collect($spelers)->pluck('id');

        $this->assertTrue($ids->contains($vanMij->id));
        $this->assertFalse($ids->contains($vanAnder->id));
    }

    /**
     * Koppelen is informatief en veel scholen doen het niet. Zou dit strikt
     * filteren, dan is het dashboard van elke trainer daar leeg.
     */
    public function test_nergens_gekoppeld_betekent_de_hele_school(): void
    {
        $een = $this->speler($this->eigenGroep);
        $twee = $this->speler($this->andereGroep);

        $ids = collect(app(TrainerDashboard::class)->players($this->trainer)['players'])->pluck('id');

        $this->assertTrue($ids->contains($een->id));
        $this->assertTrue($ids->contains($twee->id));
    }

    public function test_wie_het_langst_geen_rapport_had_staat_bovenaan(): void
    {
        $this->speler($this->eigenGroep);
        $this->speler($this->eigenGroep);

        $rijen = app(TrainerDashboard::class)->players($this->trainer);

        // Nooit beoordeeld telt als aandacht: een lege kaart is precies waarom
        // een ouder afhaakt.
        $this->assertSame(2, $rijen['stale']);
        $this->assertSame('warning', $rijen['players'][0]['tone']);
        $this->assertNull($rijen['players'][0]['days_since_report']);
    }

    public function test_geen_geld_op_het_dashboard_van_een_trainer(): void
    {
        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('widgets.kpi_revenue', null)
                ->where('widgets.finance', null)
                ->where('isTrainerOnly', true)
            );
    }

    public function test_een_ouder_krijgt_de_trainerwidgets_niet_aangeboden(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($this->speler($this->eigenGroep)->id);

        $sleutels = collect(app(WidgetRegistry::class)->describe($ouder))->pluck('key');

        $this->assertNotContains('my_trainings', $sleutels);
        $this->assertNotContains('my_players', $sleutels);
    }
}
