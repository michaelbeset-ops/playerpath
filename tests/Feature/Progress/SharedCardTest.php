<?php

namespace Tests\Feature\Progress;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De publieke deel-link is de enige route zonder inlog, en het gaat om
 * gegevens van een kind. Deze tests bewaken die grens.
 */
class SharedCardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->keeper()->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
        ]);

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->post("/players/{$this->speler->id}/reports", [
            'scores' => collect(ReportCategory::forPosition(PlayerPosition::Keeper))
                ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 8])
                ->all(),
            'note' => 'Interne notitie van de trainer.',
        ]);

        $this->speler->refresh();
    }

    public function test_delen_staat_standaard_uit(): void
    {
        $this->assertNull($this->speler->share_token);
        $this->assertFalse($this->speler->isShared());
    }

    public function test_een_eigenaar_kan_een_deel_link_aanzetten_en_weer_uit(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share")->assertRedirect();

        $token = $this->speler->refresh()->share_token;
        $this->assertNotNull($token);

        $this->get("/kaart/{$token}")->assertOk();

        $this->actingAs($this->eigenaar)->delete("/players/{$this->speler->id}/share")->assertRedirect();

        // De oude link is meteen dood.
        $this->assertNull($this->speler->refresh()->share_token);
        $this->get("/kaart/{$token}")->assertNotFound();
    }

    public function test_een_ouder_mag_de_kaart_van_het_eigen_kind_delen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($ouder->id);

        $this->actingAs($ouder)->post("/players/{$this->speler->id}/share")->assertRedirect();

        $this->assertNotNull($this->speler->refresh()->share_token);
    }

    public function test_een_trainer_mag_niet_beslissen_over_delen(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->post("/players/{$this->speler->id}/share")->assertForbidden();

        $this->assertNull($this->speler->refresh()->share_token);
    }

    public function test_een_ouder_van_een_ander_kind_mag_niet_delen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)->post("/players/{$this->speler->id}/share")->assertForbidden();
    }

    public function test_de_publieke_kaart_lekt_geen_persoonsgegevens(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");
        $token = $this->speler->refresh()->share_token;

        $response = $this->get("/kaart/{$token}");

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('players/SharedCard')
            ->where('card.name', 'Sem d.')
            ->where('card.last_name', 'd.')
            ->where('card.overall', 80)
            ->where('card.school', null)
            ->missing('player')
            ->missing('card.age')
            ->missing('card.date_of_birth')
            ->missing('lastReport')
            // De pagina staat buiten de app: geen school, geen menu, geen
            // ingelogde gebruiker in de props.
            ->where('school', null)
            ->where('nav', [])
            ->where('auth.user', null)
        );

        // Achternaam, geboortedatum, school en trainersnotitie horen hier niet.
        $response->assertDontSee('de Vries');
        $response->assertDontSee('Interne notitie');
        $response->assertDontSee($this->school->name);
    }

    public function test_een_onbekend_token_geeft_niets(): void
    {
        $this->get('/kaart/'.str_repeat('a', 48))->assertNotFound();
    }

    public function test_de_publieke_kaart_vraagt_zoekmachines_om_hem_te_negeren(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");

        // Als header, niet als meta-tag: die laatste wordt pas door JavaScript
        // toegevoegd en daar kun je bij een crawler niet op rekenen.
        $this->get('/kaart/'.$this->speler->refresh()->share_token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_opnieuw_delen_geeft_een_nieuw_token(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");
        $eerste = $this->speler->refresh()->share_token;

        $this->actingAs($this->eigenaar)->delete("/players/{$this->speler->id}/share");
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");

        $this->assertNotSame($eerste, $this->speler->refresh()->share_token);
        $this->get("/kaart/{$eerste}")->assertNotFound();
    }
}
