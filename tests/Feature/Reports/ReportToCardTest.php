<?php

namespace Tests\Feature\Reports;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het kloppend hart van fase 2: een trainer vult een rapport in en de
 * spelerskaart verandert zichtbaar mee.
 */
class ReportToCardTest extends TestCase
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

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();

        app(Tenancy::class)->set($this->school);
    }

    /** @return array<string, int> */
    protected function cijfers(int $score): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $categorie) => [$categorie->value => $score])
            ->all();
    }

    public function test_een_speler_zonder_rapporten_heeft_een_lege_kaart(): void
    {
        $this->assertNull($this->keeper->overall_rating);
        $this->assertFalse(app(CalculatePlayerCard::class)->for($this->keeper)->hasRating());
    }

    public function test_een_trainer_kan_een_rapport_opslaan_en_de_kaart_verandert_mee(): void
    {
        $response = $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/reports", [
                'scores' => $this->cijfers(8),
                'note' => 'Sterk op de lijn.',
            ]);

        $response->assertRedirect("/players/{$this->keeper->id}/card");

        $this->keeper->refresh();

        // Een 8 leest op de kaart als 80.
        $this->assertSame(80, $this->keeper->overall_rating);
        $this->assertSame(80, $this->keeper->category_ratings[ReportCategory::Reflexen->value]);
        $this->assertNotNull($this->keeper->rated_at);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_scores', 6);
    }

    public function test_een_tweede_rapport_verschuift_het_gemiddelde(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(6)]);
        $this->assertSame(60, $this->keeper->refresh()->overall_rating);

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(8)]);

        // Gemiddelde van 6 en 8 is 7, dus 70 op de kaart.
        $this->assertSame(70, $this->keeper->refresh()->overall_rating);
    }

    public function test_alleen_de_laatste_drie_rapporten_tellen_mee(): void
    {
        // Eerst een lage score die buiten beeld moet raken.
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(2)]);

        foreach ([9, 9, 9] as $cijfer) {
            $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers($cijfer)]);
        }

        $this->assertSame(90, $this->keeper->refresh()->overall_rating);
    }

    public function test_het_rapportscherm_vult_de_cijfers_van_het_vorige_rapport_voor(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(7)]);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/reports/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Create')
                ->where('previousScores.'.ReportCategory::Reflexen->value, 7)
            );
    }

    public function test_een_veldspeler_krijgt_de_veldspeler_categorieen(): void
    {
        $veldspeler = Player::factory()->for($this->school)->veldspeler()->create();

        $this->actingAs($this->trainer)
            ->get("/players/{$veldspeler->id}/reports/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/Create')
                ->where('categories.0.category', ReportCategory::Techniek->value)
                ->count('categories', 6)
            );
    }

    public function test_een_cijfer_buiten_de_schaal_wordt_geweigerd(): void
    {
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(11)])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_een_onvolledig_rapport_wordt_geweigerd(): void
    {
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/reports", [
                'scores' => [ReportCategory::Reflexen->value => 8],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_categorieen_van_de_verkeerde_positie_belanden_niet_in_het_rapport(): void
    {
        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/reports", [
                'scores' => $this->cijfers(8) + [ReportCategory::Afwerking->value => 3],
            ])
            ->assertSessionHasErrors('scores');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_de_spelerskaart_toont_de_actuele_cijfers(): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $this->cijfers(9)]);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/card")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('players/Card')
                ->where('player.overall_rating', 90)
                ->where('card.categories.0.rating', 90)
                ->where('reportCount', 1)
            );
    }
}
