<?php

namespace Tests\Feature\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het inzageverzoek: alles wat de school over één speler bewaart.
 *
 * Het bewaartermijn-scherm is weggehaald; dit is wat er van de AVG-kant
 * overblijft en dagelijks bruikbaar is. De datum waarop een speler stopte
 * wordt nog steeds vastgelegd, want dat is een feit over de speler en niet
 * een instelling die om een scherm vroeg.
 */
class PlayerDataExportTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    public function test_het_inzageverzoek_levert_een_bestand_op(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $response = $this->actingAs($this->eigenaar)->get('/players/'.$speler->id.'/gegevens');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        // Streamend antwoord: pas bij uitvoeren wordt er echt geschreven.
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_een_trainer_komt_er_niet_bij(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($trainer)->get('/players/'.$speler->id.'/gegevens')->assertForbidden();
    }

    public function test_een_speler_van_een_andere_school_is_onbereikbaar(): void
    {
        $andere = School::factory()->create();
        $vreemde = Player::factory()->for($andere)->create();

        $this->actingAs($this->eigenaar)->get('/players/'.$vreemde->id.'/gegevens')->assertNotFound();
    }

    public function test_de_knop_staat_er_alleen_voor_de_eigenaar(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/players/'.$speler->id)
            ->assertInertia(fn ($page) => $page->where('can.dataExport', true));

        $this->actingAs($trainer)
            ->get('/players/'.$speler->id)
            ->assertInertia(fn ($page) => $page->where('can.dataExport', false));
    }

    public function test_stoppen_legt_vast_wanneer_dat_gebeurde(): void
    {
        $speler = Player::factory()->for($this->school)->create(['is_active' => true]);

        $this->assertNull($speler->deactivated_at);

        $speler->update(['is_active' => false]);
        $this->assertNotNull($speler->refresh()->deactivated_at);

        // Komt hij terug, dan staat de datum er niet meer.
        $speler->update(['is_active' => true]);
        $this->assertNull($speler->refresh()->deactivated_at);
    }

    public function test_die_datum_is_niet_van_buitenaf_te_zetten(): void
    {
        $speler = Player::factory()->for($this->school)->create(['is_active' => false]);

        $gezet = $speler->refresh()->deactivated_at;

        $speler->update(['deactivated_at' => now()->subYears(5)]);

        $this->assertTrue($gezet->equalTo($speler->refresh()->deactivated_at));
    }
}
