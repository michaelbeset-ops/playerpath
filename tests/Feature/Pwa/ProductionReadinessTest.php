<?php

namespace Tests\Feature\Pwa;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'keepersschool-rob']);
        app(Tenancy::class)->set($this->school);
    }

    public function test_het_manifest_is_zonder_inlog_op_te_halen(): void
    {
        // Anders is de app niet installeerbaar zodra een sessie verloopt.
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/dashboard');
    }

    public function test_het_manifest_draagt_de_naam_en_kleur_van_de_school(): void
    {
        $this->school->update(['brand_color' => '#7B1FA2']);

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->get('/manifest.webmanifest')
            ->assertJsonPath('name', 'Keepersschool Rob')
            ->assertJsonPath('theme_color', '#7B1FA2');
    }

    public function test_het_manifest_heeft_de_iconen_die_android_en_apple_nodig_hebben(): void
    {
        $manifest = $this->get('/manifest.webmanifest')->json();

        $doelen = collect($manifest['icons'])->pluck('purpose')->all();

        $this->assertContains('any', $doelen);
        // Zonder maskable knipt Android het icoon af tot een vierkantje.
        $this->assertContains('maskable', $doelen);

        foreach ($manifest['icons'] as $icoon) {
            $this->assertFileExists(public_path(ltrim($icoon['src'], '/')), $icoon['src'].' ontbreekt');
        }
    }

    public function test_de_offlinepagina_is_zonder_inlog_bereikbaar(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Offline'));
    }

    public function test_de_service_worker_bewaart_geen_gegevens(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // De regel uit het bouwplan, hier hard gemaakt: alleen assets en het
        // offline-scherm mogen in de cache, nooit een antwoord met data.
        $this->assertStringContainsString("startsWith('/build/')", $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertStringNotContainsString('/players', $sw);
        $this->assertStringNotContainsString('/payments', $sw);
    }

    public function test_elk_antwoord_draagt_de_beveiligingskoppen(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_staat_uit_buiten_productie(): void
    {
        // Lokaal zou dit je browser dwingen playerpath.test over https te
        // openen, en dat krijg je er maanden niet meer uit.
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_de_publieke_kaart_is_begrensd(): void
    {
        $speler = Player::factory()->for($this->school)->create();
        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)->post("/players/{$speler->id}/share");
        $token = $speler->refresh()->share_token;

        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'players.shared');

        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        $this->assertNotNull($token);
    }

    public function test_de_controle_op_de_omgeving_draait(): void
    {
        $this->artisan('playerpath:check')->assertSuccessful();
    }

    public function test_de_controle_klaagt_over_debug_op_productie(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $this->artisan('playerpath:check')->assertFailed();
    }
}
