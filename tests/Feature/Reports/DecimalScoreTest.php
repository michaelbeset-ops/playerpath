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
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cijfers met een decimaal, en afronden naar boven.
 *
 * Twee dingen die hier echt toe doen: een 7,4 blijft een 7,4 in de database, en
 * naar buiten wordt er altijd naar boven afgerond. Zou er ergens gewoon
 * afgerond worden, dan laat het ene scherm 74 zien waar het andere 75 zegt, en
 * dan gelooft niemand het meer.
 */
class DecimalScoreTest extends TestCase
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

        $this->speler = Player::factory()->for($this->school)->keeper()->create();
    }

    /**
     * Eén rapport met per categorie een eigen cijfer.
     *
     * @param  list<float>  $cijfers
     */
    protected function rapporteer(array $cijfers): void
    {
        $categorieen = ReportCategory::forPosition(PlayerPosition::Keeper);

        $scores = [];

        foreach ($categorieen as $index => $categorie) {
            $scores[$categorie->value] = $cijfers[$index] ?? $cijfers[0];
        }

        $this->actingAs($this->trainer)
            ->post("/players/{$this->speler->id}/reports", ['scores' => $scores])
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------
    // Opslaan
    // ---------------------------------------------------------------

    public function test_een_cijfer_met_een_decimaal_blijft_precies_staan(): void
    {
        $this->rapporteer([7.4]);

        $this->assertSame(7.4, (float) $this->speler->reports()->first()->scores()->first()->score);
    }

    public function test_twee_decimalen_worden_geweigerd(): void
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 7.45])
            ->all();

        $this->actingAs($this->trainer)
            ->post("/players/{$this->speler->id}/reports", ['scores' => $scores])
            ->assertSessionHasErrors();
    }

    public function test_een_cijfer_buiten_de_schaal_wordt_geweigerd(): void
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 10.5])
            ->all();

        $this->actingAs($this->trainer)
            ->post("/players/{$this->speler->id}/reports", ['scores' => $scores])
            ->assertSessionHasErrors();
    }

    // ---------------------------------------------------------------
    // Afronden
    // ---------------------------------------------------------------

    public function test_afronden_gaat_altijd_naar_boven(): void
    {
        $this->assertSame(75, CalculatePlayerCard::afronden(74.5));
        $this->assertSame(75, CalculatePlayerCard::afronden(74.1));
        // Een heel getal blijft zichzelf; niet stilletjes een punt erbij.
        $this->assertSame(74, CalculatePlayerCard::afronden(74));
        $this->assertSame(74, CalculatePlayerCard::afronden(74.0));
        $this->assertNull(CalculatePlayerCard::afronden(null));
    }

    public function test_het_kaartcijfer_wordt_naar_boven_afgerond(): void
    {
        // Zes categorieën: 7,4 · 7,5 · 7 · 7 · 7 · 7 geeft gemiddeld 7,15 → 71,5.
        $this->rapporteer([7.4, 7.5, 7, 7, 7, 7]);

        // Naar boven, in het voordeel van het kind.
        $this->assertSame(72, $this->speler->fresh()->overall_rating);
    }

    public function test_de_precieze_waarde_blijft_intern_bewaard(): void
    {
        $this->rapporteer([7.4, 7.5, 7, 7, 7, 7]);

        $ratings = $this->speler->fresh()->category_ratings;

        // Niet afgerond in de opslag: hier hangt de voortgang aan.
        $this->assertSame(74.0, (float) $ratings['reflexen']);
        $this->assertSame(75.0, (float) $ratings['uitkomen']);
    }

    public function test_de_kaart_toont_de_afgeronde_categoriecijfers(): void
    {
        // Twee rapporten met 7,4 en 7,5 geven per categorie gemiddeld 74,5.
        $this->rapporteer([7.4]);
        $this->rapporteer([7.5]);

        $breakdown = collect(app(CalculatePlayerCard::class)->breakdown($this->speler->fresh()));

        $this->assertSame(75, $breakdown->firstWhere('category', 'reflexen')['rating']);
    }

    public function test_de_spelerskaart_en_het_profiel_tonen_hetzelfde_getal(): void
    {
        $this->rapporteer([7.4, 7.5, 7, 7, 7, 7]);

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $kaart = $this->actingAs($eigenaar)->get("/players/{$this->speler->id}/card");
        $profiel = $this->actingAs($eigenaar)->get("/players/{$this->speler->id}");

        $kaart->assertInertia(fn ($page) => $page->where('player.overall_rating', 72));
        $profiel->assertInertia(fn ($page) => $page->where('player.overall_rating', 72));
    }

    public function test_een_heel_cijfer_werkt_nog_gewoon(): void
    {
        $this->rapporteer([8]);

        $this->assertSame(80, $this->speler->fresh()->overall_rating);
    }
}
