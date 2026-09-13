<?php

namespace Tests\Feature\Players;

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
 * De kind-link: de kaart voor het kind zelf, zonder inlog. Anders dan de
 * publieke deel-link staat hier de hele kaart op, maar niet wat een trainer
 * over het kind opschreef. Deze tests bewaken beide grenzen.
 */
class ChildCardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['name' => 'Keepersschool Rob']);

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

    public function test_de_kind_link_staat_standaard_uit(): void
    {
        $this->assertNull($this->speler->child_token);
        $this->assertFalse($this->speler->hasChildLink());

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}/card")
            ->assertInertia(fn ($page) => $page->where('childLink.can', true)->where('childLink.url', null));
    }

    public function test_een_ouder_maakt_de_link_voor_het_eigen_kind_en_de_kaart_staat_er_helemaal_op(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($ouder->id);

        $this->actingAs($ouder)->post("/players/{$this->speler->id}/kind-link")->assertRedirect()->assertSessionHas('status');

        $token = $this->speler->refresh()->child_token;
        $this->assertNotNull($token);
        $this->assertSame(48, strlen($token));

        // De deel-link blijft los hiervan uit.
        $this->assertNull($this->speler->share_token);

        $response = $this->get("/kind/{$token}");

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('players/ChildCard')
            ->where('card.name', 'Sem de Vries')
            ->where('card.overall', 80)
            ->where('card.school', 'Keepersschool Rob')
            ->where('schoolName', 'Keepersschool Rob')
            ->where('card.recent_reports.0.note', null)
            ->where('card.recent_reports.0.trainer', null)
            ->has('badges')
            ->has('seasons')
            ->where('manifestUrl', route('players.child.manifest', $token))
            // Buiten de app: geen menu, geen ingelogde gebruiker.
            ->where('auth.user', null)
            ->where('nav', [])
        );

        $response->assertDontSee('Interne notitie');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        // Het eigen manifest staat in de kop, zodat "op het beginscherm" de kaart opent.
        $response->assertSee('/kind/'.$token.'/manifest.webmanifest');
    }

    public function test_het_manifest_opent_op_de_kaart_van_het_kind(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $token = $this->speler->refresh()->child_token;

        $this->get("/kind/{$token}/manifest.webmanifest")
            ->assertOk()
            ->assertJsonPath('name', 'Kaart van Sem')
            ->assertJsonPath('start_url', "/kind/{$token}")
            ->assertJsonPath('display', 'standalone');
    }

    public function test_een_trainer_of_een_andere_ouder_mag_geen_kind_link_maken(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->post("/players/{$this->speler->id}/kind-link")->assertForbidden();

        $andereOuder = User::factory()->for($this->school)->create();
        $andereOuder->assignRole(Role::Ouder->value);
        $this->actingAs($andereOuder)->post("/players/{$this->speler->id}/kind-link")->assertForbidden();

        $this->assertNull($this->speler->refresh()->child_token);
    }

    public function test_opnieuw_maken_geeft_een_nieuw_token_en_uitzetten_maakt_de_link_dood(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $eerste = $this->speler->refresh()->child_token;

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $tweede = $this->speler->refresh()->child_token;

        $this->assertNotSame($eerste, $tweede);
        $this->get("/kind/{$eerste}")->assertNotFound();
        $this->get("/kind/{$tweede}")->assertOk();

        $this->actingAs($this->eigenaar)->delete("/players/{$this->speler->id}/kind-link")->assertRedirect();

        $this->assertNull($this->speler->refresh()->child_token);
        $this->get("/kind/{$tweede}")->assertNotFound();
    }

    public function test_een_speler_die_stopt_houdt_geen_open_links(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");
        $speler = $this->speler->refresh();
        $kind = $speler->child_token;
        $deel = $speler->share_token;

        $speler->update(['is_active' => false]);

        $this->assertNull($speler->refresh()->child_token);
        $this->assertNull($speler->share_token);
        $this->get("/kind/{$kind}")->assertNotFound();
        $this->get("/kaart/{$deel}")->assertNotFound();
    }

    public function test_een_onbekend_token_geeft_niets(): void
    {
        $this->get('/kind/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/kind/'.str_repeat('a', 48).'/manifest.webmanifest')->assertNotFound();
    }
}
