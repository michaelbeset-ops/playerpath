<?php

namespace Tests\Feature\Branding;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Branding\Branding;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    public function test_de_eigenaar_stelt_een_merkkleur_in(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/branding', ['brand_color' => '#7B1FA2'])
            ->assertRedirect();

        $this->assertSame('#7B1FA2', $this->school->refresh()->brand_color);
    }

    public function test_een_onzinnige_kleur_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/branding', ['brand_color' => 'paars'])
            ->assertSessionHasErrors('brand_color');

        $this->assertNull($this->school->refresh()->brand_color);
    }

    public function test_de_kleur_komt_als_css_variabele_in_de_pagina(): void
    {
        $this->school->update(['brand_color' => '#7B1FA2']);

        $response = $this->actingAs($this->eigenaar)->get('/dashboard');

        // Vóór het eerste beeld, dus in de HTML zelf en niet pas in JavaScript.
        $response->assertSee('--primary:', escape: false);
        $response->assertSee('--primary-foreground:', escape: false);
    }

    public function test_zonder_eigen_kleur_wordt_er_niets_overschreven(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertDontSee('--primary:', escape: false);
    }

    public function test_een_logo_wordt_opgeslagen_en_vervangen(): void
    {
        Storage::fake('public');

        $this->actingAs($this->eigenaar)
            ->post('/branding', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertRedirect();

        $eerste = $this->school->refresh()->logo_path;
        $this->assertNotNull($eerste);
        Storage::disk('public')->assertExists($eerste);

        $this->actingAs($this->eigenaar)->post('/branding', ['logo' => UploadedFile::fake()->image('nieuw.png')]);

        // Het oude bestand wordt opgeruimd; anders groeit de schijf vol.
        Storage::disk('public')->assertMissing($eerste);
        Storage::disk('public')->assertExists($this->school->refresh()->logo_path);
    }

    public function test_een_te_groot_bestand_wordt_geweigerd(): void
    {
        Storage::fake('public');

        $this->actingAs($this->eigenaar)
            ->post('/branding', ['logo' => UploadedFile::fake()->create('groot.png', 2048, 'image/png')])
            ->assertSessionHasErrors('logo');
    }

    public function test_een_trainer_mag_de_huisstijl_niet_wijzigen(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/branding')->assertForbidden();
        $this->actingAs($trainer)->post('/branding', ['brand_color' => '#000000'])->assertForbidden();
    }

    public function test_het_subdomein_bepaalt_de_branding_van_de_inlogpagina(): void
    {
        config(['app.domain' => 'playerpath.nl']);
        $this->school->update(['brand_color' => '#7B1FA2']);

        $gevonden = app(Branding::class)->fromHost('keepersschool-rob.playerpath.nl');

        $this->assertTrue($this->school->is($gevonden));
    }

    public function test_een_onbekend_of_inactief_subdomein_levert_niets_op(): void
    {
        config(['app.domain' => 'playerpath.nl']);

        $this->assertNull(app(Branding::class)->fromHost('bestaatniet.playerpath.nl'));
        $this->assertNull(app(Branding::class)->fromHost('playerpath.nl'));
        $this->assertNull(app(Branding::class)->fromHost('a.b.playerpath.nl'));

        $this->school->update(['is_active' => false]);
        $this->assertNull(app(Branding::class)->fromHost('keepersschool-rob.playerpath.nl'));
    }

    public function test_zonder_basisdomein_wordt_er_geen_subdomein_geraden(): void
    {
        config(['app.domain' => null]);

        $this->assertNull(app(Branding::class)->fromHost('keepersschool-rob.playerpath.nl'));
    }

    /**
     * De belangrijkste test van deze fase: het adres mag nooit bepalen welke
     * data je ziet. Dat blijft het ingelogde account. Zie CLAUDE.md 3.1.
     */
    public function test_het_subdomein_van_een_andere_school_geeft_geen_toegang_tot_die_data(): void
    {
        config(['app.domain' => 'playerpath.nl']);

        $andere = School::factory()->create(['slug' => 'voetbalschool-yoel']);
        $vreemdeSpeler = Player::factory()->for($andere)->create();
        $eigenSpeler = Player::factory()->for($this->school)->create();

        // Ingelogd als eigenaar van school A, maar binnengekomen op het adres
        // van school B.
        $response = $this->actingAs($this->eigenaar)
            ->withServerVariables(['HTTP_HOST' => 'voetbalschool-yoel.playerpath.nl'])
            ->get('/users');

        $response->assertOk();

        // Zijn eigen speler staat er; die van de andere school niet.
        $response->assertSee($eigenSpeler->first_name);
        $response->assertDontSee($vreemdeSpeler->first_name);

        // En de speler van die andere school blijft onbereikbaar.
        $this->actingAs($this->eigenaar)
            ->withServerVariables(['HTTP_HOST' => 'voetbalschool-yoel.playerpath.nl'])
            ->get("/players/{$vreemdeSpeler->id}")
            ->assertNotFound();
    }

    public function test_de_ingelogde_gebruiker_wint_van_het_subdomein_voor_de_branding(): void
    {
        config(['app.domain' => 'playerpath.nl']);

        $andere = School::factory()->create(['slug' => 'voetbalschool-yoel', 'name' => 'Voetbalschool Yoel']);

        $this->actingAs($this->eigenaar)
            ->withServerVariables(['HTTP_HOST' => 'voetbalschool-yoel.playerpath.nl'])
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('branding.name', $this->school->name));
    }

    public function test_het_inschrijfformulier_toont_de_school_uit_de_url(): void
    {
        $this->school->update(['brand_color' => '#7B1FA2']);

        $this->get("/inschrijven/{$this->school->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('branding.name', $this->school->name));
    }

    public function test_de_gedeelde_kaart_verraadt_geen_school(): void
    {
        $speler = Player::factory()->for($this->school)->create();
        $this->school->update(['brand_color' => '#7B1FA2', 'name' => 'Keepersschool Rob']);

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/share");
        $token = $speler->refresh()->share_token;

        $response = $this->get("/kaart/{$token}");

        $response->assertOk();

        // Wél een huisstijl, maar die van PlayerPath: geen naam, geen logo en
        // geen kleur waaraan je de school zou kunnen herkennen.
        $response->assertInertia(fn ($page) => $page
            ->where('branding.name', 'PlayerPath')
            ->where('branding.logo', null)
            ->where('branding.color', null)
            ->where('branding.primary', null)
        );
        $response->assertDontSee('Keepersschool Rob');
    }
}
