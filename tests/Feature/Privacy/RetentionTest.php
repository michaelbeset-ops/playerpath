<?php

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
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

    protected function gestopteSpeler(int $maandenGeleden): Player
    {
        $speler = Player::factory()->for($this->school)->create(['is_active' => true]);

        $this->travelTo(now()->subMonths($maandenGeleden), fn () => $speler->update(['is_active' => false]));

        return $speler->refresh();
    }

    public function test_stoppen_zet_de_klok_van_de_bewaartermijn(): void
    {
        $speler = Player::factory()->for($this->school)->create(['is_active' => true]);

        $this->assertNull($speler->deactivated_at);

        $speler->update(['is_active' => false]);
        $this->assertNotNull($speler->refresh()->deactivated_at);

        // Komt hij terug, dan telt er niets meer af.
        $speler->update(['is_active' => true]);
        $this->assertNull($speler->refresh()->deactivated_at);
    }

    public function test_de_datum_is_niet_van_buitenaf_te_zetten(): void
    {
        $speler = Player::factory()->for($this->school)->create(['is_active' => false]);

        $gezet = $speler->refresh()->deactivated_at;

        $speler->update(['deactivated_at' => now()->subYears(5)]);

        $this->assertTrue($gezet->equalTo($speler->refresh()->deactivated_at));
    }

    public function test_zonder_ingestelde_termijn_wordt_er_niets_gesignaleerd(): void
    {
        $this->gestopteSpeler(60);

        $this->actingAs($this->eigenaar)
            ->get('/privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('retention.retentionMonths', null)
                ->count('retention.players', 0)
                ->where('retention.inactiveCount', 1)
            );
    }

    public function test_alleen_verstreken_termijnen_komen_in_de_lijst(): void
    {
        $this->school->update(['retention_months' => 24]);

        $oud = $this->gestopteSpeler(30);
        $recent = $this->gestopteSpeler(6);
        $actief = Player::factory()->for($this->school)->create(['is_active' => true]);

        $this->actingAs($this->eigenaar)
            ->get('/privacy')
            ->assertInertia(fn ($page) => $page
                ->count('retention.players', 1)
                ->where('retention.players.0.id', $oud->id)
                ->where('retention.waitingCount', 1)
            );

        $this->assertDatabaseHas('players', ['id' => $recent->id]);
        $this->assertDatabaseHas('players', ['id' => $actief->id]);
    }

    public function test_de_eigenaar_stelt_de_termijn_in(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/privacy', ['retention_months' => 24])
            ->assertRedirect();

        $this->assertSame(24, $this->school->refresh()->retention_months);

        // Leegmaken mag: dan is er nog niets besloten.
        $this->actingAs($this->eigenaar)->patch('/privacy', ['retention_months' => null]);
        $this->assertNull($this->school->refresh()->retention_months);
    }

    public function test_een_onzinnige_termijn_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/privacy', ['retention_months' => 500])
            ->assertSessionHasErrors('retention_months');
    }

    public function test_verwijderen_neemt_de_speler_en_zijn_rapporten_mee(): void
    {
        $this->school->update(['retention_months' => 12]);
        $speler = $this->gestopteSpeler(24);

        $this->actingAs($this->eigenaar)
            ->delete('/privacy/players/'.$speler->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('players', ['id' => $speler->id]);
        $this->assertDatabaseMissing('reports', ['player_id' => $speler->id]);
    }

    public function test_het_inzageverzoek_levert_een_bestand_op(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $response = $this->actingAs($this->eigenaar)->get('/players/'.$speler->id.'/gegevens');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        // Streamend antwoord: pas bij uitvoeren wordt er echt geschreven.
        $inhoud = $response->streamedContent();
        $this->assertNotEmpty($inhoud);
    }

    public function test_een_trainer_komt_er_niet_bij(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($trainer)->get('/privacy')->assertForbidden();
        $this->actingAs($trainer)->patch('/privacy', ['retention_months' => 12])->assertForbidden();
        $this->actingAs($trainer)->get('/players/'.$speler->id.'/gegevens')->assertForbidden();
        $this->actingAs($trainer)->delete('/privacy/players/'.$speler->id)->assertForbidden();
    }

    public function test_een_speler_van_een_andere_school_is_onbereikbaar(): void
    {
        $andere = School::factory()->create();
        $vreemde = Player::factory()->for($andere)->create();

        $this->actingAs($this->eigenaar)->get('/players/'.$vreemde->id.'/gegevens')->assertNotFound();
        $this->actingAs($this->eigenaar)->delete('/privacy/players/'.$vreemde->id)->assertNotFound();
    }

    public function test_het_menu_toont_privacy_alleen_aan_de_eigenaar(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => collect($nav)->contains('href', '/privacy')));

        $this->actingAs($trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => ! collect($nav)->contains('href', '/privacy')));
    }
}
