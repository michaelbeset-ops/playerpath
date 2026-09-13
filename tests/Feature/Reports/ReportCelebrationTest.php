<?php

namespace Tests\Feature\Reports;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Rating\RatingEngine;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wat de trainer te zien krijgt na het opslaan van een rapport.
 *
 * De cijfers in de viering moeten dezelfde zijn als op de kaart. Zou dit apart
 * rekenen, dan zegt de viering "+3" waar de kaart "+2" toont.
 */
class ReportCelebrationTest extends TestCase
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

        $this->speler = Player::factory()->for($this->school)->keeper()->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
        ]);
    }

    /** @return TestResponse */
    protected function rapporteer(float $cijfer)
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $cijfer])
            ->all();

        return $this->actingAs($this->trainer)->post("/players/{$this->speler->id}/reports", ['scores' => $scores]);
    }

    public function test_de_viering_vertelt_wat_er_veranderde(): void
    {
        $this->rapporteer(6);

        $this->rapporteer(9)->assertSessionHas('reportResult', function (array $result) {
            // Kaart: eerst 60, daarna het gemiddelde van 60 en 90 = 75.
            $this->assertSame(60, $result['overall']['from']);
            $this->assertSame(75, $result['overall']['to']);
            $this->assertSame(15, $result['overall']['delta']);

            // Alle zes categorieën gingen 15 punten omhoog.
            $this->assertCount(6, $result['categories']);
            $this->assertSame(15, $result['categories'][0]['delta']);
            $this->assertSame('Sem', $result['player']['first_name']);

            // Vijf voor het rapport plus dertig voor de groei (het plafond).
            $this->assertSame(35, $result['xp']['gained']);

            return true;
        });
    }

    public function test_een_level_omhoog_wordt_gevierd(): void
    {
        // Vlak onder zilver: dit rapport duwt hem eroverheen. Via de engine,
        // want `players.xp` is de som van de boekingen en wordt bij elke
        // bijschrijving opnieuw uitgerekend - met de hand zetten houdt geen stand.
        app(RatingEngine::class)->award($this->speler, 'attendance', 249, 'Trainingen tot nu toe');

        $this->rapporteer(7)->assertSessionHas('reportResult', function (array $result) {
            $this->assertTrue($result['level']['up']);
            $this->assertSame('brons', $result['level']['from']['key']);
            $this->assertSame('zilver', $result['level']['to']['key']);

            return true;
        });
    }

    public function test_zonder_level_omhoog_wordt_er_niets_gevierd(): void
    {
        $this->rapporteer(7)->assertSessionHas('reportResult', function (array $result) {
            $this->assertFalse($result['level']['up']);
            // Het eerste rapport is een nieuwe mijlpaal.
            $this->assertSame('eerste_rapport', $result['badges'][0]['key']);

            return true;
        });
    }

    public function test_de_volgende_speler_zonder_rapport_vandaag_wordt_voorgesteld(): void
    {
        $ander = Player::factory()->for($this->school)->create(['first_name' => 'Aad', 'last_name' => 'Bakker']);

        $this->rapporteer(7)->assertSessionHas('reportResult', function (array $result) use ($ander) {
            $this->assertSame($ander->id, $result['next']['id']);
            $this->assertSame('Aad Bakker', $result['next']['name']);

            return true;
        });
    }

    public function test_zonder_volgende_speler_staat_er_geen_knop(): void
    {
        $this->rapporteer(7)->assertSessionHas('reportResult', function (array $result) {
            $this->assertNull($result['next']);

            return true;
        });
    }
}
